<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfImageConverterService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{
     *     temporary_directory: string,
     *     images: array<int, array{page: int, path: string}>
     * }
     */
    public function convertToPngPages(string $absolutePdfPath): array
    {
        if (! is_file($absolutePdfPath) || ! is_readable($absolutePdfPath)) {
            throw new RuntimeException("PDF file is not readable: {$absolutePdfPath}");
        }

        $relativeDirectory = 'ocr/'.(string) Str::uuid();
        Storage::disk('local')->makeDirectory($relativeDirectory);

        $absoluteDirectory = Storage::disk('local')->path($relativeDirectory);
        $outputPrefix = $absoluteDirectory.DIRECTORY_SEPARATOR.'page';

        try {
            $process = new Process([
                $this->binary(),
                '-png',
                '-r',
                (string) $this->dpi(),
                $absolutePdfPath,
                $outputPrefix,
            ], timeout: $this->timeout());

            $process->mustRun();

            $images = $this->collectImages($absoluteDirectory);

            if ($images === []) {
                throw new RuntimeException('pdftoppm did not create any page images.');
            }

            return [
                'temporary_directory' => $relativeDirectory,
                'images' => $images,
            ];
        } catch (ProcessFailedException $exception) {
            $this->cleanup($relativeDirectory);

            Log::error('PDF page image conversion failed.', [
                'pdf_path' => $absolutePdfPath,
                'command' => $exception->getProcess()->getCommandLine(),
                'error' => $exception->getProcess()->getErrorOutput(),
            ]);

            throw new RuntimeException('Unable to convert PDF pages to images: '.$exception->getMessage(), previous: $exception);
        } catch (Throwable $exception) {
            $this->cleanup($relativeDirectory);

            Log::error('PDF page image conversion failed.', [
                'pdf_path' => $absolutePdfPath,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Unable to convert PDF pages to images: '.$exception->getMessage(), previous: $exception);
        }
    }

    public function cleanup(string $relativeDirectory): void
    {
        if ($relativeDirectory === '' || ! str_starts_with($relativeDirectory, 'ocr/')) {
            return;
        }

        Storage::disk('local')->deleteDirectory($relativeDirectory);
    }

    private function collectImages(string $absoluteDirectory): array
    {
        $files = glob($absoluteDirectory.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
        natsort($files);

        $images = [];

        foreach ($files as $file) {
            if (! preg_match('/page-(\d+)\.png$/', basename($file), $matches)) {
                continue;
            }

            $images[] = [
                'page' => (int) $matches[1],
                'path' => $file,
            ];
        }

        return $images;
    }

    private function binary(): string
    {
        $binary = config('services.ocr.pdftoppm_binary');

        return is_string($binary) && trim($binary) !== '' ? trim($binary) : 'pdftoppm';
    }

    private function dpi(): int
    {
        return $this->settings->ocrPdfDpi();
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.ocr.pdftoppm_timeout', 300));
    }
}
