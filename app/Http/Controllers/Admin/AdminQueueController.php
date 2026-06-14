<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminQueueController extends Controller
{
    public function index(): View
    {
        return view('admin.queues.index', [
            'queueStats' => $this->queueStats(),
            'failedJobsCount' => $this->failedJobsCount(),
            'latestFailedJobTime' => $this->latestFailedJobTime(),
            'hasJobsTable' => Schema::hasTable('jobs'),
            'hasFailedJobsTable' => Schema::hasTable('failed_jobs'),
        ]);
    }

    public function failedJobs(): View
    {
        return view('admin.failed-jobs.index', [
            'failedJobs' => $this->failedJobsQuery()
                ->latest('failed_at')
                ->paginate(15)
                ->through(fn (object $job): object => $this->decorateFailedJob($job))
                ->withQueryString(),
            'failedJobsCount' => $this->failedJobsCount(),
        ]);
    }

    public function retryFailedJob(int $failedJob): RedirectResponse
    {
        $job = $this->findFailedJob($failedJob);

        if (! $job) {
            return back()->with('error', 'Failed job was not found.');
        }

        $exitCode = Artisan::call('queue:retry', [
            'id' => [$job->uuid],
        ]);

        return back()->with(
            $exitCode === 0 ? 'success' : 'error',
            $exitCode === 0 ? 'Failed job retry queued.' : 'Failed job retry could not be queued.'
        );
    }

    public function retryAllFailedJobs(): RedirectResponse
    {
        if ($this->failedJobsCount() === 0) {
            return back()->with('error', 'There are no failed jobs to retry.');
        }

        $exitCode = Artisan::call('queue:retry', [
            'id' => ['all'],
        ]);

        return back()->with(
            $exitCode === 0 ? 'success' : 'error',
            $exitCode === 0 ? 'All failed job retries were queued.' : 'Failed jobs could not be retried.'
        );
    }

    public function forgetFailedJob(int $failedJob): RedirectResponse
    {
        $job = $this->findFailedJob($failedJob);

        if (! $job) {
            return back()->with('error', 'Failed job was not found.');
        }

        $exitCode = Artisan::call('queue:forget', [
            'id' => $job->uuid,
        ]);

        return back()->with(
            $exitCode === 0 ? 'success' : 'error',
            $exitCode === 0 ? 'Failed job deleted.' : 'Failed job could not be deleted.'
        );
    }

    private function queueStats(): Collection
    {
        if (! Schema::hasTable('jobs')) {
            return collect();
        }

        return DB::table('jobs')
            ->select('queue')
            ->selectRaw('count(*) as total_jobs')
            ->selectRaw('sum(case when reserved_at is null then 1 else 0 end) as pending_jobs')
            ->selectRaw('sum(case when reserved_at is not null then 1 else 0 end) as reserved_jobs')
            ->selectRaw('min(available_at) as next_available_at')
            ->selectRaw('max(created_at) as latest_created_at')
            ->groupBy('queue')
            ->orderBy('queue')
            ->get()
            ->map(fn (object $queue): object => (object) [
                'queue' => $queue->queue,
                'total_jobs' => (int) $queue->total_jobs,
                'pending_jobs' => (int) $queue->pending_jobs,
                'reserved_jobs' => (int) $queue->reserved_jobs,
                'next_available_at' => $queue->next_available_at ? Carbon::createFromTimestamp((int) $queue->next_available_at) : null,
                'latest_created_at' => $queue->latest_created_at ? Carbon::createFromTimestamp((int) $queue->latest_created_at) : null,
            ]);
    }

    private function failedJobsQuery()
    {
        if (! Schema::hasTable('failed_jobs')) {
            return DB::query()->fromRaw('(select null as id, null as uuid, null as connection, null as queue, null as exception, null as failed_at) as empty_failed_jobs')->whereRaw('1 = 0');
        }

        return DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at']);
    }

    private function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')->count();
    }

    private function latestFailedJobTime(): ?Carbon
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        $failedAt = DB::table('failed_jobs')->max('failed_at');

        return $failedAt ? Carbon::parse($failedAt) : null;
    }

    private function findFailedJob(int $id): ?object
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        return DB::table('failed_jobs')
            ->select(['id', 'uuid'])
            ->where('id', $id)
            ->first();
    }

    private function decorateFailedJob(object $job): object
    {
        return (object) [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'failed_at' => $job->failed_at ? Carbon::parse($job->failed_at) : null,
            'exception_preview' => $this->sanitizeExceptionPreview((string) $job->exception),
        ];
    }

    private function sanitizeExceptionPreview(string $exception): string
    {
        $firstLine = trim(str($exception)->replace(["\r\n", "\r"], "\n")->before("\n")->toString());
        $firstLine = preg_replace('/\b[A-Za-z]:[\\\\\/][^\s]+/u', '[path hidden]', $firstLine) ?? $firstLine;
        $firstLine = preg_replace('/(?<!:)\/(?:[^\s\/]+\/)+[^\s]+/u', '[path hidden]', $firstLine) ?? $firstLine;
        $firstLine = preg_replace('/(?i)(api[_-]?key|token|secret|password)(\s*[:=]\s*)[^\s,;]+/u', '$1$2[hidden]', $firstLine) ?? $firstLine;

        return str($firstLine === '' ? 'No exception preview available.' : $firstLine)->limit(220)->toString();
    }
}
