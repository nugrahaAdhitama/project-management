<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;
    protected $developerRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view_user']);
        Permission::create(['name' => 'create_user']);
        Permission::create(['name' => 'update_user']);
        Permission::create(['name' => 'delete_user']);

        // Create roles
        $this->superAdminRole = Role::create(['name' => 'super_admin']);
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->memberRole = Role::create(['name' => 'member']);
        $this->developerRole = Role::create(['name' => 'developer']);

        // Assign permissions to roles
        $this->superAdminRole->givePermissionTo(['view_user', 'create_user', 'update_user', 'delete_user']);
        $this->adminRole->givePermissionTo(['view_user', 'create_user', 'update_user']);
        $this->memberRole->givePermissionTo(['view_user']);
        $this->developerRole->givePermissionTo(['view_user', 'create_user']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================
    // BULK ROLE ASSIGNMENT - REPLACE MODE TESTS
    // ============================================

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

    // ============================================
    // BULK ROLE ASSIGNMENT - ADD MODE TESTS
    // ============================================

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
        $this->assertCount(3, $user3->roles); // member, developer, super_admin (developer sudah ada)
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

    // ============================================
    // PERMISSION VERIFICATION AFTER BULK ASSIGNMENT
    // ============================================

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

    // ============================================
    // EDGE CASES & VALIDATION TESTS
    // ============================================

    public function testBulkAssignmentWorksWithSingleUser(): void
    {
        // Arrange: Create single user
        $user = User::factory()->create();
        $user->assignRole('member');

        // Act: Bulk assign to single user with REPLACE mode
        $data = [
            'roles' => [$this->adminRole->id],
            'role_mode' => 'replace'
        ];

        $user->roles()->sync($data['roles']);

        // Assert
        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('member'));
    }

    public function testBulkAssignmentWorksWithLargeNumberOfUsers(): void
    {
        // Arrange: Create many users
        $users = User::factory()->count(50)->create();

        // Act: Bulk assign 'developer' role with ADD mode to all
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
        }
    }

    public function testBulkAssignmentPreservesUserData(): void
    {
        // Arrange: Create users with specific data
        $user1 = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        $user1->assignRole('member');

        $user2 = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ]);
        $user2->assignRole('member');

        $users = [$user1, $user2];

        // Act: Bulk assign roles
        $data = [
            'roles' => [$this->adminRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: User data should remain unchanged
        $user1->refresh();
        $this->assertEquals('John Doe', $user1->name);
        $this->assertEquals('john@example.com', $user1->email);

        $user2->refresh();
        $this->assertEquals('Jane Smith', $user2->name);
        $this->assertEquals('jane@example.com', $user2->email);
    }

    public function testBulkAssignmentWithMixedRoleStates(): void
    {
        // Arrange: Create users with mixed role states
        $user1 = User::factory()->create(); // No roles

        $user2 = User::factory()->create();
        $user2->assignRole('member'); // One role

        $user3 = User::factory()->create();
        $user3->assignRole(['admin', 'developer']); // Multiple roles

        $users = [$user1, $user2, $user3];

        // Act: Bulk assign with REPLACE mode
        $data = [
            'roles' => [$this->superAdminRole->id, $this->memberRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: All users should have same roles now
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('super_admin'));
            $this->assertTrue($user->hasRole('member'));
            $this->assertFalse($user->hasRole('admin'));
            $this->assertFalse($user->hasRole('developer'));
            $this->assertCount(2, $user->roles);
        }
    }

    public function testBulkAssignmentReturnsCorrectCounts(): void
    {
        // Arrange: Create users
        $users = User::factory()->count(5)->create();

        // Act: Bulk assign roles
        $data = [
            'roles' => [$this->memberRole->id],
            'role_mode' => 'add'
        ];

        $assignedCount = 0;
        foreach ($users as $user) {
            $user->roles()->syncWithoutDetaching($data['roles']);
            $assignedCount++;
        }

        // Assert: Count should match
        $this->assertEquals(5, $assignedCount);

        // Verify all users have the role
        $usersWithRole = User::role('member')->count();
        $this->assertEquals(5, $usersWithRole);
    }

    public function testCanVerifyRoleAssignmentInDatabase(): void
    {
        // Arrange: Create users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $users = [$user1, $user2];

        // Act: Bulk assign roles
        $data = [
            'roles' => [$this->adminRole->id, $this->memberRole->id],
            'role_mode' => 'replace'
        ];

        foreach ($users as $user) {
            $user->roles()->sync($data['roles']);
        }

        // Assert: Verify in database
        foreach ($users as $user) {
            $this->assertDatabaseHas('model_has_roles', [
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'role_id' => $this->adminRole->id
            ]);

            $this->assertDatabaseHas('model_has_roles', [
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'role_id' => $this->memberRole->id
            ]);
        }
    }

    // ============================================
    // COMPARISON BETWEEN REPLACE AND ADD MODES
    // ============================================

    public function testCompareReplaceModeVsAddMode(): void
    {
        // Arrange: Create two users with same initial role
        $userForReplace = User::factory()->create();
        $userForReplace->assignRole(['member', 'developer']);

        $userForAdd = User::factory()->create();
        $userForAdd->assignRole(['member', 'developer']);

        // Verify initial state
        $this->assertCount(2, $userForReplace->roles);
        $this->assertCount(2, $userForAdd->roles);

        // Act: Apply different modes
        // Replace mode
        $userForReplace->roles()->sync([$this->adminRole->id]);

        // Add mode
        $userForAdd->roles()->syncWithoutDetaching([$this->adminRole->id]);

        // Assert: Different results
        $userForReplace->refresh();
        $this->assertTrue($userForReplace->hasRole('admin'));
        $this->assertFalse($userForReplace->hasRole('member'));
        $this->assertFalse($userForReplace->hasRole('developer'));
        $this->assertCount(1, $userForReplace->roles);

        $userForAdd->refresh();
        $this->assertTrue($userForAdd->hasRole('admin'));
        $this->assertTrue($userForAdd->hasRole('member'));
        $this->assertTrue($userForAdd->hasRole('developer'));
        $this->assertCount(3, $userForAdd->roles);
    }

    public function testBulkOperationMimicsFilamentResourceLogic(): void
    {
        // This test mimics the exact logic from UserResource.php bulk action

        // Arrange
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $user->assignRole('member');
        }

        // Simulate Filament bulk action data
        $data = [
            'roles' => [$this->adminRole->id, $this->developerRole->id],
            'role_mode' => 'add'
        ];

        // Act: Execute the same logic as in UserResource.php
        foreach ($users as $record) {
            if ($data['role_mode'] === 'replace') {
                $record->roles()->sync($data['roles']);
            } else {
                $record->roles()->syncWithoutDetaching($data['roles']);
            }
        }

        // Assert
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('member')); // original
            $this->assertTrue($user->hasRole('admin')); // added
            $this->assertTrue($user->hasRole('developer')); // added
            $this->assertCount(3, $user->roles);
        }
    }
}
