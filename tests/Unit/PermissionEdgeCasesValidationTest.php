<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionEdgeCasesValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'update_user']);
    }

    // ============================================
    // EDGE CASES & VALIDATION TESTS
    // ============================================

    public function testCannotAssignNonExistentPermission(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert
        $this->expectException(PermissionDoesNotExist::class);
        $user->givePermissionTo('non_existent_permission');
    }

    public function testGetPermissionNamesReturnsCorrectList(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user', 'update_user']);

        // Act
        $permissionNames = $user->getPermissionNames();

        // Assert
        $this->assertCount(3, $permissionNames);
        $this->assertTrue($permissionNames->contains('view_user'));
        $this->assertTrue($permissionNames->contains('create_user'));
        $this->assertTrue($permissionNames->contains('update_user'));
    }

    public function testPermissionCacheWorks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');

        // Act: First check loads from database and caches
        $user->hasPermissionTo('view_user');

        // Give another permission
        $user->givePermissionTo('create_user');

        // Assert: Cache should be refreshed
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('create_user'));
    }

    public function testPermissionsRelationshipWorksCorrectly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user']);

        // Act
        $permissions = $user->permissions;

        // Assert
        $this->assertNotNull($permissions);
        $this->assertCount(2, $permissions);
    }
}
