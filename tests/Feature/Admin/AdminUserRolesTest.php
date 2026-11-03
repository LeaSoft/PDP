<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_grant_and_revoke_roles(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['super_admin' => true])->save();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->putJson("/admin/users/{$user->id}/roles.json", ['super_admin' => true, 'is_moderator' => true])
            ->assertOk()
            ->assertJson(['user_id' => $user->id, 'super_admin' => true, 'is_moderator' => true]);

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'update_user_roles',
            'target_id' => $user->id,
        ]);

        $this->actingAs($admin)
            ->putJson("/admin/users/{$user->id}/roles.json", ['is_moderator' => false])
            ->assertOk()
            ->assertJson(['super_admin' => true, 'is_moderator' => false]);
    }

    public function test_non_admin_cannot_change_roles(): void
    {
        $moderator = User::factory()->create(['is_moderator' => true]);
        $user = User::factory()->create();

        $this->actingAs($moderator)
            ->putJson("/admin/users/{$user->id}/roles.json", ['super_admin' => true])
            ->assertForbidden();

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }

    public function test_super_admin_cannot_revoke_own_admin_role(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['super_admin' => true])->save();

        $this->actingAs($admin)
            ->putJson("/admin/users/{$admin->id}/roles.json", ['super_admin' => false])
            ->assertUnprocessable();

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }
}
