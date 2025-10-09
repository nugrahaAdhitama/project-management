<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionViaRolesTest extends TestCase
{
    use RefreshDatabase;

    protected $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'create_ticket']);
        (new Permission())->create(['name' => 'update_ticket']);

        // Create roles
        $this->adminRole = (new Role())->create(['name' => 'admin']);
    }

    // ============================================
    // PERMISSION VIA ROLE TESTS
    // ============================================

    public function testGetPermissionsViaRoles(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user', 'create_user']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Act
        $permissions = $user->getPermissionsViaRoles();

        // Assert
        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->contains('name', 'view_user'));
        $this->assertTrue($permissions->contains('name', 'create_user'));
    }

    public function testGetDirectPermissions(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(['create_ticket', 'update_ticket']); // direct permissions

        // Act
        $directPermissions = $user->getDirectPermissions();

        // Assert
        $this->assertCount(2, $directPermissions);
        $this->assertTrue($directPermissions->contains('name', 'create_ticket'));
        $this->assertTrue($directPermissions->contains('name', 'update_ticket'));
        $this->assertFalse($directPermissions->contains('name', 'view_user')); // role permission not included
    }

    public function testGetAllPermissionsIncludesBothDirectAndRole(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user', 'create_user']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(['create_ticket', 'update_ticket']); // direct

        // Act
        $allPermissions = $user->getAllPermissions();

        // Assert
        $this->assertCount(4, $allPermissions);
        $this->assertTrue($allPermissions->contains('name', 'view_user')); // role
        $this->assertTrue($allPermissions->contains('name', 'create_user')); // role
        $this->assertTrue($allPermissions->contains('name', 'create_ticket')); // direct
        $this->assertTrue($allPermissions->contains('name', 'update_ticket')); // direct
    }
}
