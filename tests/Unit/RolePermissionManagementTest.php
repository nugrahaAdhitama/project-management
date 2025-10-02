<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

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

        // Create roles
        $this->adminRole = (new Role())->create(['name' => 'admin']);
        $this->memberRole = (new Role())->create(['name' => 'member']);

        // Assign permissions to roles
        $this->adminRole->givePermissionTo(['create_user', 'view_user', 'update_user', 'view_project']);
        $this->memberRole->givePermissionTo(['view_user', 'view_project']);
    }

    // ============================================
    // ROLE PERMISSION MANAGEMENT TESTS
    // ============================================

    public function testCanAssignPermissionsToRole(): void
    {
        // Arrange
        $role = (new Role())->create(['name' => 'custom_role']);
        $permission = (new Permission())->create(['name' => 'custom_permission']);

        // Act
        $role->givePermissionTo($permission);

        // Assert
        $this->assertTrue($role->hasPermissionTo('custom_permission'));
    }

    public function testCanRevokePermissionsFromRole(): void
    {
        // Arrange
        $role = (new Role())->create(['name' => 'custom_role']);
        $permission = (new Permission())->create(['name' => 'custom_permission']);
        $role->givePermissionTo($permission);
        $this->assertTrue($role->hasPermissionTo('custom_permission'));

        // Act
        $role->revokePermissionTo($permission);

        // Assert
        $this->assertFalse($role->hasPermissionTo('custom_permission'));
    }

    public function testCanSyncPermissionsToRole(): void
    {
        // Arrange
        $role = (new Role())->create(['name' => 'custom_role']);
        $perm1 = (new Permission())->create(['name' => 'permission_1']);
        $perm2 = (new Permission())->create(['name' => 'permission_2']);
        $perm3 = (new Permission())->create(['name' => 'permission_3']);

        $role->givePermissionTo([$perm1, $perm2]);

        // Act: Sync to only perm2 and perm3
        $role->syncPermissions([$perm2, $perm3]);

        // Assert
        $this->assertFalse($role->hasPermissionTo('permission_1'));
        $this->assertTrue($role->hasPermissionTo('permission_2'));
        $this->assertTrue($role->hasPermissionTo('permission_3'));
    }

    public function testUserWithMultipleRolesHasCombinedPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act: Assign both admin and member roles
        $user->assignRole(['admin', 'member']);

        // Assert: User should have combined permissions from both roles
        $this->assertTrue($user->can('create_user')); // from admin
        $this->assertTrue($user->can('view_user')); // from both
        $this->assertTrue($user->can('view_project')); // from both
        $this->assertFalse($user->can('delete_user')); // neither has this
    }
}
