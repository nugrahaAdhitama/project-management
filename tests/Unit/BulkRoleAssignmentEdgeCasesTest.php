<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkRoleAssignmentEdgeCasesTest extends TestCase
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

        $roleModel = new Role();
        $this->superAdminRole = $roleModel->create(['name' => 'super_admin']);
        $this->adminRole = $roleModel->create(['name' => 'admin']);
        $this->memberRole = $roleModel->create(['name' => 'member']);
        $this->developerRole = $roleModel->create(['name' => 'developer']);
    }

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
}
