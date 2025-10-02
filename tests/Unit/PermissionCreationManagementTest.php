<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionCreationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'view_any_user']);
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'update_user']);
        (new Permission())->create(['name' => 'delete_user']);
        (new Permission())->create(['name' => 'view_project']);
        (new Permission())->create(['name' => 'create_project']);
    }

    // ============================================
    // PERMISSION CREATION & MANAGEMENT TESTS
    // ============================================

    public function testCanCreatePermission(): void
    {
        // Arrange
        $permissionName = 'custom_permission';

        // Act
        $permission = (new Permission())->create(['name' => $permissionName]);

        // Assert
        $this->assertNotNull($permission);
        $this->assertEquals($permissionName, $permission->name);
        $this->assertDatabaseHas('permissions', ['name' => $permissionName]);
    }

    public function testCanRetrieveAllPermissions(): void
    {
        // Act
        $permissions = (new Permission())->all();

        // Assert: Should have at least the permissions created in setUp
        $this->assertGreaterThanOrEqual(7, $permissions->count());
    }

    public function testCanFindPermissionByName(): void
    {
        // Act
        $permission = Permission::findByName('view_user');

        // Assert
        $this->assertNotNull($permission);
        $this->assertEquals('view_user', $permission->name);
    }

    public function testCanDeletePermission(): void
    {
        // Arrange
        $permission = (new Permission())->create(['name' => 'temporary_permission']);
        $permissionId = $permission->id;

        // Act
        $permission->delete();

        // Assert
        $this->assertDatabaseMissing('permissions', ['id' => $permissionId]);
    }

    public function testCanGetPermissionsByPrefix(): void
    {
        // Act
        $viewPermissions = (new Permission())->where('name', 'like', 'view%')->get();
        $createPermissions = (new Permission())->where('name', 'like', 'create%')->get();

        // Assert
        $this->assertGreaterThan(0, $viewPermissions->count());
        $this->assertGreaterThan(0, $createPermissions->count());
    }
}
