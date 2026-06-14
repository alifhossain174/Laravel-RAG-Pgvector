<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_filter_users(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin One',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
        $suspendedUser = User::factory()->create([
            'name' => 'Suspended Search Target',
            'email' => 'target@example.com',
            'is_suspended' => true,
        ]);
        User::factory()->create([
            'name' => 'Active Other',
            'email' => 'other@example.com',
        ]);

        $suspendedUser->documents()->create([
            'title' => 'Owned Document',
            'original_filename' => 'owned.pdf',
            'file_path' => 'documents/'.$suspendedUser->id.'/owned.pdf',
            'status' => Document::STATUS_READY,
        ]);
        Conversation::query()->create([
            'user_id' => $suspendedUser->id,
            'title' => 'Owned Conversation',
            'scope' => Conversation::SCOPE_SELECTED,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'target', 'filter' => 'suspended']))
            ->assertOk()
            ->assertSeeText('Suspended Search Target')
            ->assertSeeText('target@example.com')
            ->assertSeeText('Suspended')
            ->assertSeeText('1')
            ->assertDontSeeText('Active Other');
    }

    public function test_admin_can_view_user_detail_without_private_paths_or_message_content(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Detail User',
            'email' => 'detail@example.com',
        ]);
        $document = $user->documents()->create([
            'title' => 'Visible Title',
            'original_filename' => 'visible.pdf',
            'file_path' => 'documents/'.$user->id.'/private-visible.pdf',
            'file_size' => 4096,
            'status' => Document::STATUS_FAILED,
            'failed_reason' => 'Secret path C:\\private\\visible.pdf',
        ]);
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Visible Conversation',
            'scope' => Conversation::SCOPE_ALL,
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'content' => 'Private message content should not render.',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSeeText('Detail User')
            ->assertSeeText('detail@example.com')
            ->assertSeeText('Storage Used')
            ->assertSeeText('4.0 KB')
            ->assertSeeText('Visible Title')
            ->assertSeeText('visible.pdf')
            ->assertSeeText('Visible Conversation')
            ->assertSeeText('1 messages')
            ->assertDontSee($document->file_path)
            ->assertDontSee('private-visible.pdf')
            ->assertDontSee('Secret path')
            ->assertDontSee('Private message content should not render.');
    }

    public function test_admin_can_promote_demote_suspend_and_activate_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Managed User',
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.promote', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Managed User is now an admin.');

        $this->assertTrue($user->fresh()->is_admin);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.demote', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Managed User is now a normal user.');

        $this->assertFalse($user->fresh()->is_admin);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.suspend', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Managed User has been suspended.');

        $this->assertTrue($user->fresh()->is_suspended);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.activate', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'Managed User has been activated.');

        $this->assertFalse($user->fresh()->is_suspended);
    }

    public function test_admin_self_protection_blocks_demote_and_suspend(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_suspended' => false,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.demote', $admin))
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot remove your own admin role.');

        $this->assertTrue($admin->fresh()->is_admin);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.suspend', $admin))
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot suspend your own admin account.');

        $this->assertFalse($admin->fresh()->is_suspended);
    }
}
