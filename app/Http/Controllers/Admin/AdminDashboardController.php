<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $userMetrics = [
            ['label' => 'Total Users', 'value' => User::query()->count(), 'helper' => 'Registered accounts'],
            ['label' => 'Active Users', 'value' => User::query()->where('is_suspended', false)->count(), 'helper' => 'Not suspended'],
            ['label' => 'Suspended Users', 'value' => User::query()->where('is_suspended', true)->count(), 'helper' => 'Blocked from workspace'],
            ['label' => 'Admin Users', 'value' => User::query()->where('is_admin', true)->count(), 'helper' => 'Can access admin'],
        ];

        $statusCounts = Document::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $documentMetrics = collect([
            ['label' => 'Total Documents', 'value' => Document::query()->count(), 'helper' => 'All uploaded records'],
        ])->merge(collect(Document::STATUSES)->map(fn (string $status): array => [
            'label' => Str::of($status)->replace('_', ' ')->title()->toString(),
            'value' => (int) ($statusCounts[$status] ?? 0),
            'helper' => 'Documents with this status',
        ]))->values();

        $totalChunks = DocumentChunk::query()->count();
        $embeddedChunks = DocumentChunk::query()->whereNotNull('embedded_at')->count();

        return view('admin.dashboard', [
            'userMetrics' => $userMetrics,
            'documentMetrics' => $documentMetrics,
            'chunkMetrics' => [
                ['label' => 'Total Chunks', 'value' => $totalChunks, 'helper' => 'Searchable text segments'],
                ['label' => 'Embedded Chunks', 'value' => $embeddedChunks, 'helper' => 'Embedding timestamp present'],
                ['label' => 'Missing Embedding Chunks', 'value' => max(0, $totalChunks - $embeddedChunks), 'helper' => 'Still awaiting embeddings'],
            ],
            'conversationMetrics' => [
                ['label' => 'Total Conversations', 'value' => Conversation::query()->count(), 'helper' => 'Chat workspaces'],
                ['label' => 'Total Messages', 'value' => Message::query()->count(), 'helper' => 'User and assistant messages'],
            ],
            'queueMetrics' => [
                ['label' => 'Pending Jobs', 'value' => $this->tableCount('jobs'), 'helper' => 'Queued job records'],
                ['label' => 'Failed Jobs', 'value' => $this->tableCount('failed_jobs'), 'helper' => 'Failed queue records'],
                ['label' => 'Storage Used', 'value' => $this->formatBytes((int) Document::query()->sum('file_size')), 'helper' => 'Approximate uploaded file size'],
            ],
            'fileTypeDistribution' => $this->fileTypeDistribution(),
            'latestUsers' => User::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'is_admin', 'is_suspended', 'created_at']),
            'latestDocuments' => Document::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'user_id', 'title', 'original_filename', 'mime_type', 'file_size', 'status', 'created_at']),
            'latestFailedDocuments' => Document::query()
                ->with('user:id,name,email')
                ->where('status', Document::STATUS_FAILED)
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'user_id', 'title', 'original_filename', 'status', 'created_at', 'processed_at']),
            'latestConversations' => Conversation::query()
                ->with('user:id,name,email')
                ->withCount('messages')
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'user_id', 'title', 'scope', 'created_at']),
            'latestFailedJobs' => $this->latestFailedJobs(),
        ]);
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    /**
     * @return Collection<int, array{label: string, mime_type: string, extension: string, count: int}>
     */
    private function fileTypeDistribution(): Collection
    {
        return Document::query()
            ->get(['mime_type', 'original_filename'])
            ->groupBy(function (Document $document): string {
                $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
                $mimeType = $document->mime_type ?: 'unknown';

                return ($extension ?: 'unknown').'|'.$mimeType;
            })
            ->map(function (Collection $documents, string $key): array {
                [$extension, $mimeType] = explode('|', $key, 2);

                return [
                    'label' => strtoupper($extension === 'unknown' ? 'Unknown' : $extension),
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'count' => $documents->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(8)
            ->values();
    }

    private function latestFailedJobs(): Collection
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'failed_at'])
            ->latest('failed_at')
            ->limit(5)
            ->get()
            ->map(fn (object $job): object => (object) [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at ? Carbon::parse($job->failed_at) : null,
            ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $index);

        return number_format($value, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
