<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionRevocationSyncTest extends TestCase
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
    // PERMISSION REVOCATION TESTS
    // ============================================

    public function testCanRevokeDirectPermissionFromUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');
        $this->assertTrue($user->hasPermissionTo('view_user'));

        // Act
        $user->revokePermissionTo('view_user');

        // Assert
        $this->assertFalse($user->hasPermissionTo('view_user'));
    }

    public function testCanRevokeMultiplePermissionsFromUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user', 'update_user']);
        $this->assertCount(3, $user->permissions);

        // Act
        $user->revokePermissionTo(['view_user', 'create_user']);

        // Assert
        $this->assertFalse($user->hasPermissionTo('view_user'));
        $this->assertFalse($user->hasPermissionTo('create_user'));
        $this->assertTrue($user->hasPermissionTo('update_user'));
        $this->assertCount(1, $user->fresh()->permissions);
    }

    // ============================================
    // PERMISSION SYNC TESTS
    // ============================================

    public function testCanSyncPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user', 'update_user']);

        // Act: Sync to only view_user and delete_user
        $user->syncPermissions(['view_user', 'delete_user']);

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('delete_user'));
        $this->assertFalse($user->hasPermissionTo('create_user'));
        $this->assertFalse($user->hasPermissionTo('update_user'));
        $this->assertCount(2, $user->fresh()->permissions);
    }

    public function testSyncPermissionsReplacesAllExisting(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user', 'update_user', 'delete_user']);

        // Act: Sync to empty array
        $user->syncPermissions([]);

        // Assert
        $this->assertCount(0, $user->fresh()->permissions);
    }
}
