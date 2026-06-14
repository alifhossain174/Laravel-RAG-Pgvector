<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_verified_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_unverified_admin_is_redirected_to_email_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_verified_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin access confirmed')
            ->assertSee('Usage Limits')
            ->assertSee('Failed Jobs');
    }
}
