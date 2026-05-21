<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_constants_are_defined(): void
    {
        $this->assertSame('owner', User::ROLE_OWNER);
        $this->assertSame('admin', User::ROLE_ADMIN);
        $this->assertSame('finance', User::ROLE_FINANCE);
        $this->assertSame('warehouse', User::ROLE_WAREHOUSE);
        $this->assertSame('customer_service', User::ROLE_CUSTOMER_SERVICE);
        $this->assertSame('content_manager', User::ROLE_CONTENT_MANAGER);
        $this->assertSame('user', User::ROLE_USER);
    }

    public function test_staff_roles_list_contains_all_non_user_roles(): void
    {
        $this->assertContains('owner', User::STAFF_ROLES);
        $this->assertContains('admin', User::STAFF_ROLES);
        $this->assertContains('finance', User::STAFF_ROLES);
        $this->assertContains('warehouse', User::STAFF_ROLES);
        $this->assertContains('customer_service', User::STAFF_ROLES);
        $this->assertContains('content_manager', User::STAFF_ROLES);
        $this->assertNotContains('user', User::STAFF_ROLES);
    }

    public function test_is_staff_returns_true_for_admin_roles(): void
    {
        foreach (User::STAFF_ROLES as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($user->isStaff(), "Role {$role} should be staff");
        }
    }

    public function test_is_staff_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->assertFalse($user->isStaff());
    }

    public function test_has_role_returns_true_for_matching_role(): void
    {
        $user = User::factory()->create(['role' => 'finance']);
        $this->assertTrue($user->hasRole('finance'));
        $this->assertTrue($user->hasRole('admin', 'finance'));
        $this->assertFalse($user->hasRole('owner', 'admin'));
    }

    public function test_is_owner_or_admin_returns_true_for_owner_and_admin(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $finance = User::factory()->create(['role' => 'finance']);

        $this->assertTrue($owner->isOwnerOrAdmin());
        $this->assertTrue($admin->isOwnerOrAdmin());
        $this->assertFalse($finance->isOwnerOrAdmin());
    }

    public function test_regular_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin');
        // Filament v3+ redirect (302) user yang `canAccessPanel() === false` ke
        // login panel, bukan return 403. Yang penting: user TIDAK bisa render
        // halaman admin (bukan 200). Validasi tegas via canAccessPanel di
        // assertion kedua.
        $this->assertContains(
            $response->getStatusCode(),
            [302, 403],
            'User biasa harus diblok dari /admin (302 redirect atau 403 forbidden).'
        );
        $this->assertNotSame(200, $response->getStatusCode());

        $panel = \Filament\Facades\Filament::getPanel('admin');
        $this->assertFalse($user->canAccessPanel($panel));
    }

    public function test_admin_user_can_access_admin_panel(): void
    {
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // Test langsung via canAccessPanel() karena Filament panel
        // memerlukan session/cookie yang tidak tersedia di HTTP test
        $panel = \Filament\Facades\Filament::getPanel('admin');
        $this->assertTrue($admin->canAccessPanel($panel));
    }
}
