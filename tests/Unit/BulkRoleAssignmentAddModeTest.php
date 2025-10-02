<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkRoleAssignmentAddModeTest extends TestCase
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

    public function testCanBulkAssignRoleWithAddModeToMultipleUsers(): void
    {
        // Arrange: Create users with existing roles
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $user3 = User::factory()->create();
        $user3->assignRole('member');

        $users = [$user1, $user2, $user3];

        // Act: Bulk assign 'admin' role with ADD mode
        $data = [
            'roles' => [$this->adminRole->id],
            'role_mode' => 'add'
        ];

        foreach ($users as $user) {
            if ($data['role_mode'] === 'replace') {
                $user->roles()->sync($data['roles']);
            } else {
                $user->roles()->syncWithoutDetaching($data['roles']);
            }
        }

        // Assert: All users should have both 'member' and 'admin' roles
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('member'));
            $this->assertTrue($user->hasRole('admin'));
            $this->assertCount(2, $user->roles);
        }
    }

    public function testBulkAssignMultipleRolesWithAddModeAddsToExistingRoles(): void
    {
        // Arrange: Create users with existing roles
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $user2 = User::factory()->create();
        $user2->assignRole('admin');

        $user3 = User::factory()->create();
        $user3->assignRole(['member', 'developer']);

        $users = [$user1, $user2, $user3];

        // Act: Bulk assign 'super_admin' and 'developer' roles with ADD mode
        $data = [
            'roles' => [$this->superAdminRole->id, $this->developerRole->id],
            'role_mode' => 'add'
        ];

        foreach ($users as $user) {
            $user->roles()->syncWithoutDetaching($data['roles']);
        }

        // Assert: Users should have original + new roles
        $user1->refresh();
        $this->assertTrue($user1->hasRole('member'));
        $this->assertTrue($user1->hasRole('super_admin'));
        $this->assertTrue($user1->hasRole('developer'));
        $this->assertCount(3, $user1->roles);

        $user2->refresh();
        $this->assertTrue($user2->hasRole('admin'));
        $this->assertTrue($user2->hasRole('super_admin'));
        $this->assertTrue($user2->hasRole('developer'));
        $this->assertCount(3, $user2->roles);

        $user3->refresh();
        $this->assertTrue($user3->hasRole('member'));
        $this->assertTrue($user3->hasRole('developer'));
        $this->assertTrue($user3->hasRole('super_admin'));
        $this->assertCount(3, $user3->roles);
    }

    public function testBulkAssignWithAddModeOnUsersWithNoExistingRoles(): void
    {
        // Arrange: Create users without any roles
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $users = [$user1, $user2, $user3];

        // Act: Bulk assign 'developer' role with ADD mode
        $data = [
            'roles' => [$this->developerRole->id],
            'role_mode' => 'add'
        ];

        foreach ($users as $user) {
            $user->roles()->syncWithoutDetaching($data['roles']);
        }

        // Assert: All users should have 'developer' role
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('developer'));
            $this->assertCount(1, $user->roles);
        }
    }

    public function testBulkAssignDuplicateRoleWithAddModeDoesNotDuplicate(): void
    {
        // Arrange: Create users with 'member' role
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $users = [$user1, $user2];

        // Act: Try to bulk assign 'member' role again with ADD mode
        $data = [
            'roles' => [$this->memberRole->id],
            'role_mode' => 'add'
        ];

        foreach ($users as $user) {
            $user->roles()->syncWithoutDetaching($data['roles']);
        }

        // Assert: Users should still have only 1 'member' role (no duplicate)
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('member'));
            $this->assertCount(1, $user->roles);
        }
    }

    public function testUsersGainAdditionalPermissionsAfterBulkRoleAssignmentWithAddMode(): void
    {
        // Arrange: Create users with 'member' role
        $user1 = User::factory()->create();
        $user1->assignRole('member');

        $user2 = User::factory()->create();
        $user2->assignRole('member');

        $users = [$user1, $user2];

        // Act: Bulk assign 'developer' role with ADD mode
        $data = [
            'roles' => [$this->developerRole->id],
            'role_mode' => 'add'
        ];

        foreach ($users as $user) {
            $user->roles()->syncWithoutDetaching($data['roles']);
        }

        // Assert: Users should have combined permissions from both roles
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->can('view_user')); // from member
            $this->assertTrue($user->can('create_user')); // from developer
            $this->assertFalse($user->can('delete_user')); // neither has delete
        }
    }
}
