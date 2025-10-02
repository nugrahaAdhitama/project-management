<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup basic permissions for testing
        (new Permission())->create(['name' => 'delete_user']);

        // Create roles
        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
        $this->adminRole = (new Role())->create(['name' => 'admin']);
        $this->memberRole = (new Role())->create(['name' => 'member']);

        // Assign permissions to roles
        $this->superAdminRole->givePermissionTo(['delete_user']);
    }

    // ============================================
    // ROLE REMOVAL TESTS
    // ============================================

    public function testCanRemoveRoleFromUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('member');
        $this->assertTrue($user->hasRole('member'));

        // Act
        $user->removeRole('member');

        // Assert
        $this->assertFalse($user->hasRole('member'));
        $this->assertCount(0, $user->roles);
    }

    public function testCanRemoveMultipleRolesFromUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'member']);
        $this->assertCount(2, $user->roles);

        // Act
        $user->removeRole(['admin', 'member']);

        // Assert
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('member'));
        $this->assertCount(0, $user->roles);
    }

    public function testRemovingRoleRevokesPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->assertTrue($user->can('delete_user'));

        // Act
        $user->removeRole('super_admin');

        // Assert: User should lose permissions
        $this->assertFalse($user->can('delete_user'));
    }
}
