<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkRoleAssignmentReplaceModeTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;
    protected $developerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $permissionModel = new Permission();
        $permissionModel->create(['name' => 'view_user']);
        $permissionModel->create(['name' => 'create_user']);
        $permissionModel->create(['name' => 'update_user']);
        $permissionModel->create(['name' => 'delete_user']);

        $roleModel = new Role();
        $this->superAdminRole = $roleModel->create(['name' => 'super_admin']);
        $this->adminRole = $roleModel->create(['name' => 'admin']);
        $this->memberRole = $roleModel->create(['name' => 'member']);
        $this->developerRole = $roleModel->create(['name' => 'developer']);

        $this->superAdminRole->givePermissionTo(['view_user', 'create_user', 'update_user', 'delete_user']);
        $this->adminRole->givePermissionTo(['view_user', 'create_user', 'update_user']);
        $this->memberRole->givePermissionTo(['view_user']);
        $this->developerRole->givePermissionTo(['view_user', 'create_user']);
    }

    public function testCanBulkAssignRoleWithReplaceModeToMultipleUsers(): void
    {
        // Arrange: Create users with existing roles
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $user3 = User::factory()->create();
        $user3->assignRole('member');

        $users = [$user1, $user2, $user3];

        // Verify initial state
        foreach ($users as $user) {
            $this->assertTrue($user->hasRole('member'));
            $this->assertCount(1, $user->roles);
        }

        // Act: Bulk assign 'admin' role with REPLACE mode
        $data = [
            'roles' => [$this->adminRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            if ($data['role_mode'] === 'replace') {
                $user->roles()->sync($data['roles']);
            } else {
                $user->roles()->syncWithoutDetaching($data['roles']);
            }
        }

        // Assert: All users should now only have 'admin' role
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('admin'));
            $this->assertFalse($user->hasRole('member'));
            $this->assertCount(1, $user->roles);
        }
    }

    public function testBulkAssignMultipleRolesWithReplaceModeReplacesExistingRoles(): void
    {
        // Arrange: Create users with different existing roles
        $user1 = User::factory()->create();
        $user1->assignRole(['super_admin', 'admin']);

        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $user3 = User::factory()->create();
        $user3->assignRole(['developer', 'member']);

        $users = [$user1, $user2, $user3];

        // Act: Bulk assign 'admin' and 'developer' roles with REPLACE mode
        $data = [
            'roles' => [$this->adminRole->id, $this->developerRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: All users should only have 'admin' and 'developer' roles
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('admin'));
            $this->assertTrue($user->hasRole('developer'));
            $this->assertFalse($user->hasRole('super_admin'));
            $this->assertFalse($user->hasRole('member'));
            $this->assertCount(2, $user->roles);
        }
    }

    public function testBulkAssignWithReplaceModeOnUsersWithNoExistingRoles(): void
    {
        // Arrange: Create users without any roles
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $users = [$user1, $user2, $user3];

        // Verify no roles initially
        foreach ($users as $user) {
            $this->assertCount(0, $user->roles);
        }

        // Act: Bulk assign 'member' role with REPLACE mode
        $data = [
            'roles' => [$this->memberRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: All users should have 'member' role
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('member'));
            $this->assertCount(1, $user->roles);
        }
    }

    public function testBulkAssignEmptyRolesWithReplaceModeRemovesAllRoles(): void
    {
        // Arrange: Create users with existing roles
        $user1 = User::factory()->create();
        $user1->assignRole(['admin', 'member']);

        $user2 = User::factory()->create();
        $user2->assignRole('developer');

        $users = [$user1, $user2];

        // Act: Bulk assign empty roles with REPLACE mode (removes all)
        $data = [
            'roles' => [],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: All users should have no roles
        foreach ($users as $user) {
            $user->refresh();
            $this->assertCount(0, $user->roles);
            $this->assertFalse($user->hasRole('admin'));
            $this->assertFalse($user->hasRole('member'));
            $this->assertFalse($user->hasRole('developer'));
        }
    }

    public function testUsersGainPermissionsAfterBulkRoleAssignmentWithReplaceMode(): void
    {
        // Arrange: Create users with 'member' role (only has view_user permission)
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $users = [$user1, $user2];

        // Verify initial permissions
        foreach ($users as $user) {
            $this->assertTrue($user->can('view_user'));
            $this->assertFalse($user->can('create_user'));
        }

        // Act: Bulk assign 'admin' role with REPLACE mode
        $data = [
            'roles' => [$this->adminRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: Users should now have admin permissions
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->can('view_user'));
            $this->assertTrue($user->can('create_user'));
            $this->assertTrue($user->can('update_user'));
            $this->assertFalse($user->can('delete_user')); // admin doesn't have delete
        }
    }
}
