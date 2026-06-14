<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminQueueManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_queue_overview_counts(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => 'queued-secret-payload',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => 'default',
                'payload' => 'reserved-secret-payload',
                'attempts' => 1,
                'reserved_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => 'embeddings',
                'payload' => 'embedding-secret-payload',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'failed-secret-payload',
            'exception' => 'RuntimeException: Failed with api_key=secret-value',
            'failed_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.queues.index'))
            ->assertOk()
            ->assertSeeText('Queue backlog by queue')
            ->assertSeeText('default')
            ->assertSeeText('embeddings')
            ->assertSeeText('Failed Jobs')
            ->assertSeeText('Database queue view')
            ->assertDontSee('queued-secret-payload')
            ->assertDontSee('reserved-secret-payload')
            ->assertDontSee('embedding-secret-payload')
            ->assertDontSee('failed-secret-payload')
            ->assertDontSee('secret-value');
    }

    public function test_admin_can_view_sanitized_failed_jobs_list(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'payload-with-secret-token',
            'exception' => "RuntimeException: Failed with api_key=super-secret at C:\\private\\document.pdf\n#0 /private/app/file.php",
            'failed_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.failed-jobs.index'))
            ->assertOk()
            ->assertSeeText('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee')
            ->assertSeeText('database')
            ->assertSeeText('default')
            ->assertSeeText('api_key=[hidden]')
            ->assertSeeText('[path hidden]')
            ->assertDontSee('payload-with-secret-token')
            ->assertDontSee('super-secret')
            ->assertDontSee('C:\\private\\document.pdf')
            ->assertDontSee('/private/app/file.php');
    }

    public function test_admin_can_retry_one_retry_all_and_forget_failed_jobs(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $firstId = DB::table('failed_jobs')->insertGetId([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => '22222222-3333-4444-5555-666666666666',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException',
            'failed_at' => now(),
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => ['11111111-2222-3333-4444-555555555555']])
            ->andReturn(0);

        $this
            ->actingAs($admin)
            ->post(route('admin.failed-jobs.retry', $firstId))
            ->assertRedirect()
            ->assertSessionHas('success', 'Failed job retry queued.');

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => ['all']])
            ->andReturn(0);

        $this
            ->actingAs($admin)
            ->post(route('admin.failed-jobs.retry-all'))
            ->assertRedirect()
            ->assertSessionHas('success', 'All failed job retries were queued.');

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:forget', ['id' => '11111111-2222-3333-4444-555555555555'])
            ->andReturn(0);

        $this
            ->actingAs($admin)
            ->delete(route('admin.failed-jobs.destroy', $firstId))
            ->assertRedirect()
            ->assertSessionHas('success', 'Failed job deleted.');
    }

    public function test_retry_all_handles_empty_failed_jobs_table(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.failed-jobs.retry-all'))
            ->assertRedirect()
            ->assertSessionHas('error', 'There are no failed jobs to retry.');
    }
}
