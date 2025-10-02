<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'view_project']);
        (new Permission())->create(['name' => 'create_user']);
    }

    // ============================================
    // BULK PERMISSION OPERATIONS TESTS
    // ============================================

    public function testCanAssignPermissionsToMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(3)->create();

        // Act: Assign same permissions to all users
        foreach ($users as $user) {
            $user->givePermissionTo(['view_user', 'view_project']);
        }

        // Assert
        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->hasPermissionTo('view_user'));
            $this->assertTrue($user->fresh()->hasPermissionTo('view_project'));
        }
    }

    public function testCanGetUsersWithSpecificPermission(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create();
        $userWithPermission->givePermissionTo('create_user');

        $anotherUserWithPermission = User::factory()->create();
        $anotherUserWithPermission->givePermissionTo('create_user');

        $userWithoutPermission = User::factory()->create();

        // Act
        $usersWithCreatePermission = User::permission('create_user')->get();

        // Assert
        $this->assertGreaterThanOrEqual(2, $usersWithCreatePermission->count());
        $this->assertTrue($usersWithCreatePermission->contains($userWithPermission));
        $this->assertTrue($usersWithCreatePermission->contains($anotherUserWithPermission));
        $this->assertFalse($usersWithCreatePermission->contains($userWithoutPermission));
    }
}
