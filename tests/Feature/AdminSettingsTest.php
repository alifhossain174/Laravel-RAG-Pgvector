<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\LlmService;
use App\Services\RagRetrievalService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_global_settings(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSeeText('Platform Controls')
            ->assertSeeText('Upload Limits')
            ->assertSeeText('RAG Settings')
            ->assertSeeText('AI Settings')
            ->assertSeeText('OCR Settings')
            ->assertSeeText('Default User Limits')
            ->assertDontSee('GEMINI_API_KEY');

        $this
            ->actingAs($admin)
            ->patch(route('admin.settings.update'), $this->settingsPayload([
                'uploads_enabled' => false,
                'chat_enabled' => false,
                'registration_enabled' => false,
                'allowed_mime_types' => ['application/pdf'],
                'rag_top_k' => 3,
                'embedding_model' => 'custom-embedding-model',
                'chat_model' => 'custom-chat-model',
                'default_daily_chat_limit' => 7,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Settings updated.');

        $settings = app(SettingsService::class);

        $this->assertFalse($settings->uploadsEnabled());
        $this->assertFalse($settings->chatEnabled());
        $this->assertFalse($settings->registrationEnabled());
        $this->assertSame(['application/pdf'], $settings->allowedUploadMimeTypes());
        $this->assertSame(3, app(RagRetrievalService::class)->limitForQuestion('What is this about?'));
        $this->assertSame('custom-embedding-model', app(EmbeddingService::class)->model());
        $this->assertSame('custom-chat-model', app(LlmService::class)->model());
        $this->assertSame(7, $settings->defaultUserLimits()['daily_chat_limit']);
        $this->assertDatabaseHas('app_settings', [
            'key' => 'uploads_enabled',
            'type' => 'boolean',
            'group' => 'Platform Controls',
        ]);
    }

    public function test_settings_validation_rejects_unsupported_mime_types(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.settings.update'), $this->settingsPayload([
                'allowed_mime_types' => ['application/x-msdownload'],
            ]))
            ->assertSessionHasErrors('settings.allowed_mime_types');
    }

    public function test_uploads_can_be_disabled_globally(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->saveSettings([
            'uploads_enabled' => false,
        ]);

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('documents.store'), [
                'document' => UploadedFile::fake()->create('blocked.pdf', 128, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['document' => 'Document uploads are currently disabled.']);

        $this->assertDatabaseCount('documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_global_allowed_mime_types_can_restrict_uploads(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->saveSettings([
            'allowed_mime_types' => ['application/pdf'],
        ]);

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('documents.store'), [
                'document' => UploadedFile::fake()->create(
                    'blocked.docx',
                    128,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ),
            ])
            ->assertSessionHasErrors(['document' => 'Please upload a PDF document.']);

        $this->assertDatabaseCount('documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_chat_and_registration_can_be_disabled_globally(): void
    {
        $this->saveSettings([
            'chat_enabled' => false,
            'registration_enabled' => false,
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Disabled chat',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => 'Can I ask this?',
            ])
            ->assertSessionHasErrors(['content' => 'Chat is currently disabled.']);

        auth()->logout();

        $this
            ->get(route('register'))
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'Registration is currently disabled.');

        $this
            ->post(route('register'), [
                'name' => 'Blocked User',
                'email' => 'blocked@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors(['email' => 'Registration is currently disabled.']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{settings: array<string, mixed>}
     */
    private function settingsPayload(array $overrides = []): array
    {
        $payload = [];

        foreach (app(SettingsService::class)->definitions() as $key => $definition) {
            $value = $overrides[$key] ?? $definition['default'];

            $payload[$key] = match ($definition['type']) {
                'boolean' => $value ? '1' : '0',
                'array' => implode("\n", is_array($value) ? $value : []),
                default => $value,
            };
        }

        return ['settings' => $payload];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveSettings(array $overrides = []): void
    {
        app(SettingsService::class)->update($this->settingsValues($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsValues(array $overrides = []): array
    {
        return collect(app(SettingsService::class)->definitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $overrides[$key] ?? $definition['default']])
            ->all();
    }
}
