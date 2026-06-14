<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{summary: array<string, int>, groups: array<int, array<string, mixed>>, latestReadyDocument: ?Document, latestFailedDocument: ?Document}
     */
    public function report(): array
    {
        $groups = [
            $this->databaseGroup(),
            $this->queueGroup(),
            $this->storageGroup(),
            $this->aiConfigurationGroup(),
            $this->pdfToolsGroup(),
            $this->ocrGroup(),
        ];

        return [
            'summary' => $this->summary($groups),
            'groups' => $groups,
            'latestReadyDocument' => $this->latestDocument(Document::STATUS_READY),
            'latestFailedDocument' => $this->latestDocument(Document::STATUS_FAILED),
        ];
    }

    /**
     * @return array{title: string, checks: array<int, array<string, string|null>>}
     */
    private function databaseGroup(): array
    {
        $checks = [];
        $driver = DB::getDriverName();

        try {
            DB::connection()->getPdo();
            $checks[] = $this->check('Database connection', 'healthy', 'Connected successfully.', "Driver: {$driver}");
        } catch (Throwable $exception) {
            $checks[] = $this->check('Database connection', 'failed', 'The application could not connect to the database.');
        }

        if ($driver === 'pgsql') {
            $checks[] = $this->check('PostgreSQL driver', 'healthy', 'PostgreSQL is active for this environment.');
            $checks[] = $this->pgvectorCheck();
            $checks[] = $this->hnswIndexCheck();
        } else {
            $checks[] = $this->check('PostgreSQL driver', 'warning', 'Current driver is not PostgreSQL.', "Driver: {$driver}");
            $checks[] = $this->check('pgvector extension', 'warning', 'Skipped because the current driver is not PostgreSQL.');
            $checks[] = $this->check('HNSW embedding index', 'warning', 'Skipped because the current driver is not PostgreSQL.');
        }

        $hasChunksTable = Schema::hasTable('document_chunks');
        $hasEmbeddingColumn = $hasChunksTable && Schema::hasColumn('document_chunks', 'embedding');

        $checks[] = $this->check(
            'document_chunks table',
            $hasChunksTable ? 'healthy' : 'failed',
            $hasChunksTable ? 'Table is present.' : 'Table is missing.'
        );

        $checks[] = $this->check(
            'Embedding column',
            $hasEmbeddingColumn ? 'healthy' : ($driver === 'pgsql' ? 'failed' : 'warning'),
            $hasEmbeddingColumn
                ? 'Embedding column is present.'
                : ($driver === 'pgsql' ? 'Embedding column is missing.' : 'Embedding column is only expected on PostgreSQL test or production databases.')
        );

        return [
            'title' => 'Database and Vector Search',
            'checks' => $checks,
        ];
    }

    private function pgvectorCheck(): array
    {
        try {
            $result = DB::selectOne("select exists(select 1 from pg_extension where extname = 'vector') as installed");
            $installed = $this->truthy($result->installed ?? false);

            return $this->check(
                'pgvector extension',
                $installed ? 'healthy' : 'failed',
                $installed ? 'pgvector is installed.' : 'pgvector extension is not installed.'
            );
        } catch (Throwable $exception) {
            return $this->check('pgvector extension', 'failed', 'Could not inspect installed PostgreSQL extensions.');
        }
    }

    private function hnswIndexCheck(): array
    {
        if (! Schema::hasTable('document_chunks') || ! Schema::hasColumn('document_chunks', 'embedding')) {
            return $this->check('HNSW embedding index', 'failed', 'document_chunks.embedding must exist before the HNSW index can be checked.');
        }

        try {
            $result = DB::selectOne(<<<'SQL'
                select exists(
                    select 1
                    from pg_indexes
                    where schemaname = current_schema()
                    and tablename = 'document_chunks'
                    and indexdef ilike '%embedding%'
                    and indexdef ilike '%hnsw%'
                ) as installed
            SQL);
            $installed = $this->truthy($result->installed ?? false);

            return $this->check(
                'HNSW embedding index',
                $installed ? 'healthy' : 'warning',
                $installed ? 'HNSW index exists for document chunk embeddings.' : 'HNSW index was not found; retrieval may slow down as data grows.'
            );
        } catch (Throwable $exception) {
            return $this->check('HNSW embedding index', 'warning', 'Could not inspect PostgreSQL indexes.');
        }
    }

    /**
     * @return array{title: string, checks: array<int, array<string, string|null>>}
     */
    private function queueGroup(): array
    {
        $hasJobsTable = Schema::hasTable('jobs');
        $hasFailedJobsTable = Schema::hasTable('failed_jobs');
        $failedJobsCount = $hasFailedJobsTable ? DB::table('failed_jobs')->count() : 0;

        return [
            'title' => 'Queues',
            'checks' => [
                $this->check('jobs table', $hasJobsTable ? 'healthy' : 'failed', $hasJobsTable ? 'Table is present.' : 'Table is missing.'),
                $this->check('failed_jobs table', $hasFailedJobsTable ? 'healthy' : 'failed', $hasFailedJobsTable ? 'Table is present.' : 'Table is missing.'),
                $this->check(
                    'Failed jobs count',
                    $failedJobsCount === 0 ? 'healthy' : 'warning',
                    $failedJobsCount === 0 ? 'No failed jobs recorded.' : number_format($failedJobsCount).' failed job(s) need review.'
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, checks: array<int, array<string, string|null>>}
     */
    private function storageGroup(): array
    {
        try {
            $disk = Storage::disk('local');
            $testFile = 'health-check/write-test.txt';

            $disk->put($testFile, Carbon::now()->toIso8601String());
            $disk->delete($testFile);

            $check = $this->check('Local storage disk', 'healthy', 'Storage disk is writable.');
        } catch (Throwable $exception) {
            $check = $this->check('Local storage disk', 'failed', 'Storage disk is not writable.');
        }

        return [
            'title' => 'Storage',
            'checks' => [$check],
        ];
    }

    /**
     * @return array{title: string, checks: array<int, array<string, string|null>>}
     */
    private function aiConfigurationGroup(): array
    {
        $apiKeyConfigured = filled(config('services.gemini.api_key'));
        $embeddingModel = $this->settings->embeddingModel();
        $embeddingDimensions = $this->settings->embeddingDimensions();
        $chatModel = $this->settings->chatModel();
        $topK = $this->settings->ragTopK();
        $maxContextChars = $this->settings->ragMaxContextChars();

        return [
            'title' => 'AI and RAG Configuration',
            'checks' => [
                $this->check('Gemini API key', $apiKeyConfigured ? 'healthy' : 'failed', $apiKeyConfigured ? 'Configured.' : 'Missing GEMINI_API_KEY.'),
                $this->check('Embedding model', filled($embeddingModel) ? 'healthy' : 'failed', filled($embeddingModel) ? 'Configured.' : 'Embedding model is missing.', filled($embeddingModel) ? $embeddingModel : null),
                $this->check('Embedding dimensions', $embeddingDimensions > 0 ? 'healthy' : 'failed', $embeddingDimensions > 0 ? 'Configured.' : 'Embedding dimensions must be greater than zero.', $embeddingDimensions > 0 ? number_format($embeddingDimensions) : null),
                $this->check('Chat model', filled($chatModel) ? 'healthy' : 'failed', filled($chatModel) ? 'Configured.' : 'Chat model is missing.', filled($chatModel) ? $chatModel : null),
                $this->check('RAG top_k', $topK > 0 ? 'healthy' : 'warning', $topK > 0 ? 'Configured.' : 'RAG_TOP_K should be greater than zero.', $topK > 0 ? number_format($topK) : null),
                $this->check('RAG max context', $maxContextChars > 0 ? 'healthy' : 'warning', $maxContextChars > 0 ? 'Configured.' : 'RAG_MAX_CONTEXT_CHARS should be greater than zero.', $maxContextChars > 0 ? number_format($maxContextChars).' chars' : null),
            ],
        ];
    }

    /**
     * @return array{title: string, checks: array<int, array<string, string|null>>}
     */
    private function pdfToolsGroup(): array
    {
        $path = config('services.pdftotext.binary');
        $available = $this->configuredBinaryAvailable($path) || $this->commandAvailable('pdftotext');
        $configured = filled($path);

        return [
            'title' => 'PDF Tools',
            'checks' => [
                $this->check(
                    'pdftotext availability',
                    $available ? 'healthy' : 'failed',
                    $available ? 'pdftotext is available.' : 'pdftotext was not found through PDFTOTEXT_PATH or the system command.',
                    $configured ? 'PDFTOTEXT_PATH configured.' : 'Using command lookup.'
                ),
            ],
        ];
    }

    /**
     * @return array{title: string, checks: array<int, array<string, string|null>>}
     */
    private function ocrGroup(): array
    {
        $enabled = $this->settings->ocrEnabled();
        $tesseractPath = config('services.ocr.tesseract_binary');
        $pdftoppmPath = config('services.ocr.pdftoppm_binary');
        $tesseractAvailable = $this->configuredBinaryAvailable($tesseractPath) || $this->commandAvailable('tesseract');
        $pdftoppmAvailable = $this->configuredBinaryAvailable($pdftoppmPath) || $this->commandAvailable('pdftoppm');
        $language = $this->settings->ocrLanguage();
        $dpi = $this->settings->ocrPdfDpi();

        return [
            'title' => 'OCR',
            'checks' => [
                $this->check('OCR enabled', $enabled ? 'healthy' : 'warning', $enabled ? 'OCR fallback is enabled.' : 'OCR fallback is disabled.'),
                $this->check(
                    'Tesseract availability',
                    $enabled ? ($tesseractAvailable ? 'healthy' : 'failed') : ($tesseractAvailable ? 'healthy' : 'warning'),
                    $tesseractAvailable ? 'Tesseract is available.' : 'Tesseract was not found through TESSERACT_PATH or the system command.',
                    filled($tesseractPath) ? 'TESSERACT_PATH configured.' : 'Using command lookup.'
                ),
                $this->check(
                    'pdftoppm availability',
                    $enabled ? ($pdftoppmAvailable ? 'healthy' : 'failed') : ($pdftoppmAvailable ? 'healthy' : 'warning'),
                    $pdftoppmAvailable ? 'pdftoppm is available.' : 'pdftoppm was not found through PDFTOPPM_PATH or the system command.',
                    filled($pdftoppmPath) ? 'PDFTOPPM_PATH configured.' : 'Using command lookup.'
                ),
                $this->check('OCR language', filled($language) ? 'healthy' : 'warning', filled($language) ? 'Configured.' : 'OCR language is empty.', filled($language) ? $language : null),
                $this->check('OCR DPI', $dpi > 0 ? 'healthy' : 'warning', $dpi > 0 ? 'Configured.' : 'OCR DPI should be greater than zero.', $dpi > 0 ? number_format($dpi).' DPI' : null),
            ],
        ];
    }

    private function latestDocument(string $status): ?Document
    {
        return Document::query()
            ->with('user:id,name,email')
            ->where('status', $status)
            ->latest($status === Document::STATUS_READY ? 'processed_at' : 'updated_at')
            ->latest('id')
            ->first(['id', 'ulid', 'user_id', 'title', 'original_filename', 'status', 'file_size', 'processed_at', 'created_at', 'updated_at']);
    }

    /**
     * @param  array<int, array{title: string, checks: array<int, array<string, string|null>>}>  $groups
     * @return array<string, int>
     */
    private function summary(array $groups): array
    {
        $summary = [
            'healthy' => 0,
            'warning' => 0,
            'failed' => 0,
        ];

        foreach ($groups as $group) {
            foreach ($group['checks'] as $check) {
                $summary[$check['status']]++;
            }
        }

        return $summary;
    }

    private function check(string $label, string $status, string $message, ?string $detail = null): array
    {
        return compact('label', 'status', 'message', 'detail');
    }

    private function configuredBinaryAvailable(mixed $path): bool
    {
        if (! is_string($path) || blank($path)) {
            return false;
        }

        return is_file($path) || is_executable($path);
    }

    private function commandAvailable(string $command): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $lookupCommand = PHP_OS_FAMILY === 'Windows'
            ? 'where '.escapeshellarg($command).' 2>NUL'
            : 'command -v '.escapeshellarg($command).' 2>/dev/null';

        $output = [];
        $exitCode = 1;
        @exec($lookupCommand, $output, $exitCode);

        return $exitCode === 0;
    }

    private function truthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }
}
