<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionCheckingTest extends TestCase
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
        (new Permission())->create(['name' => 'create_project']);
        (new Permission())->create(['name' => 'delete_project']);
        (new Permission())->create(['name' => 'view_project']);
    }

    // ============================================
    // PERMISSION CHECKING TESTS
    // ============================================

    public function testCanCheckIfUserHasPermission(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');

        // Act & Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertFalse($user->hasPermissionTo('delete_user'));
    }

    public function testCanCheckPermissionUsingCanMethod(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('create_project');

        // Act & Assert
        $this->assertTrue($user->can('create_project'));
        $this->assertFalse($user->can('delete_project'));
    }

    public function testCanCheckIfUserHasAnyPermission(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user']);

        // Act & Assert
        $this->assertTrue($user->hasAnyPermission(['view_user', 'delete_user']));
        $this->assertFalse($user->hasAnyPermission(['delete_user', 'delete_project']));
    }

    public function testCanCheckIfUserHasAllPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo(['view_user', 'create_user', 'update_user']);

        // Act & Assert
        $this->assertTrue($user->hasAllPermissions(['view_user', 'create_user']));
        $this->assertFalse($user->hasAllPermissions(['view_user', 'create_user', 'delete_user']));
    }

    public function testCanCheckPermissionViaGate(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_project');

        // Act & Assert
        $this->assertTrue(Gate::forUser($user)->allows('view_project'));
        $this->assertFalse(Gate::forUser($user)->allows('delete_project'));
    }

    public function testCanCheckPermissionWithGuard(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');

        // Act & Assert: Check permission with default guard
        $this->assertTrue($user->hasPermissionTo('view_user', 'web'));
    }
}
