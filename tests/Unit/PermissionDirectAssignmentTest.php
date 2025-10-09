<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionDirectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'update_user']);
        (new Permission())->create(['name' => 'delete_user']);
    }

    // ============================================
    // DIRECT PERMISSION ASSIGNMENT TESTS
    // ============================================

    public function testCanAssignDirectPermissionToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->givePermissionTo('view_user');

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertDatabaseHas('model_has_permissions', [
            'model_id' => $user->id,
            'model_type' => get_class($user),
        ]);
    }

    public function testCanAssignMultipleDirectPermissionsToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->givePermissionTo(['view_user', 'create_user', 'update_user']);

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('create_user'));
        $this->assertTrue($user->hasPermissionTo('update_user'));
        $this->assertCount(3, $user->permissions);
    }

    public function testCanAssignPermissionUsingPermissionObject(): void
    {
        // Arrange
        $user = User::factory()->create();
        $permission = Permission::findByName('view_user');

        // Act
        $user->givePermissionTo($permission);

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
    }

    public function testCannotAssignDuplicateDirectPermission(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');

        // Act: Try to assign same permission again
        $user->givePermissionTo('view_user');

        // Assert: Should still have only 1 direct permission
        $this->assertCount(1, $user->permissions);
    }
}
