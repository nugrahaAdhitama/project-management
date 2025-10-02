<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAssignmentToUserTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup basic permissions for testing
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'update_user']);
        (new Permission())->create(['name' => 'delete_user']);
        (new Permission())->create(['name' => 'view_project']);
        (new Permission())->create(['name' => 'create_project']);

        // Create roles
        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
        $this->adminRole = (new Role())->create(['name' => 'admin']);
        $this->memberRole = (new Role())->create(['name' => 'member']);

        // Assign permissions to roles
        $this->superAdminRole->givePermissionTo(['create_user', 'view_user', 'update_user', 'delete_user', 'view_project', 'create_project']);
        $this->adminRole->givePermissionTo(['create_user', 'view_user', 'update_user', 'view_project', 'create_project']);
        $this->memberRole->givePermissionTo(['view_user', 'view_project']);
    }

    // ============================================
    // ROLE ASSIGNMENT TO USER TESTS
    // ============================================

    public function testCanAssignSingleRoleToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole('member');

        // Assert
        $this->assertTrue($user->hasRole('member'));
        $this->assertCount(1, $user->roles);
    }

    public function testCanAssignMultipleRolesToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole(['admin', 'member']);

        // Assert
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('member'));
        $this->assertCount(2, $user->roles);
    }

    public function testCanAssignRoleUsingRoleObject(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole($this->superAdminRole);

        // Assert
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertCount(1, $user->roles);
    }

    public function testCannotAssignDuplicateRole(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('member');

        // Act: Try to assign same role again
        $user->assignRole('member');

        // Assert: Should still have only 1 role
        $this->assertCount(1, $user->roles);
    }

    public function testUserInheritsPermissionsFromRole(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole('super_admin');

        // Assert: User should have permissions from super_admin role
        $this->assertTrue($user->can('create_user'));
        $this->assertTrue($user->can('delete_user'));
        $this->assertTrue($user->can('view_project'));
        $this->assertTrue($user->can('create_project'));
    }

    public function testMemberRoleHasLimitedPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole('member');

        // Assert: Member should have limited permissions
        $this->assertTrue($user->can('view_user'));
        $this->assertTrue($user->can('view_project'));
        $this->assertFalse($user->can('create_user'));
        $this->assertFalse($user->can('delete_user'));
        $this->assertFalse($user->can('create_project'));
    }
}
