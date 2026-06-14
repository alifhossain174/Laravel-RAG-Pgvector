<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsageLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_usage_logs(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Usage User',
            'email' => 'usage@example.com',
        ]);
        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'action_type' => 'chat_response',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'status' => 'success',
        ]);
        AiUsageLog::query()->create([
            'action_type' => 'embedding_failed',
            'provider' => 'other',
            'model' => 'other-model',
            'status' => 'failed',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.usage-logs.index', [
                'user' => $user->id,
                'action_type' => 'chat_response',
                'status' => 'success',
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
            ]))
            ->assertOk()
            ->assertSeeText('Usage User')
            ->assertSeeText('usage@example.com')
            ->assertSeeText('Chat Response')
            ->assertSeeText('gemini-2.5-flash');
    }

    public function test_admin_can_view_usage_log_detail_with_redacted_metadata(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $log = AiUsageLog::query()->create([
            'action_type' => 'chat_failed',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'status' => 'failed',
            'error_message' => 'Chat failed with api_key=super-secret at C:\\private\\trace.txt',
            'metadata' => [
                'safe' => 'visible',
                'payload' => 'private-payload',
                'nested' => [
                    'file_path' => '/var/www/private.pdf',
                ],
            ],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.usage-logs.show', $log))
            ->assertOk()
            ->assertSeeText('Chat Failed')
            ->assertSeeText('api_key=[hidden]')
            ->assertSeeText('[path hidden]')
            ->assertSeeText('visible')
            ->assertDontSee('super-secret')
            ->assertDontSee('private-payload')
            ->assertDontSee('/var/www/private.pdf')
            ->assertDontSee('C:\\private\\trace.txt');
    }
}
