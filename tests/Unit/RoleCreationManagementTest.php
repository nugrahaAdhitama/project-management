<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleCreationManagementTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // ROLE CREATION TESTS
    // ============================================

    public function testCanCreateRole(): void
    {
        // Arrange
        $roleName = 'developer';

        // Act
        $role = (new Role())->create(['name' => $roleName]);

        // Assert
        $this->assertNotNull($role);
        $this->assertEquals($roleName, $role->name);
        $this->assertDatabaseHas('roles', ['name' => $roleName]);
    }

    public function testCanRetrieveAllRoles(): void
    {
        // Arrange
        (new Role())->create(['name' => 'super_admin']);
        (new Role())->create(['name' => 'admin']);
        (new Role())->create(['name' => 'member']);

        // Act
        $roles = (new Role())->all();

        // Assert: Should have 3 roles (super_admin, admin, member)
        $this->assertGreaterThanOrEqual(3, $roles->count());
        $this->assertNotNull($roles->where('name', 'super_admin')->first());
        $this->assertNotNull($roles->where('name', 'admin')->first());
        $this->assertNotNull($roles->where('name', 'member')->first());
    }

    public function testCanFindRoleByName(): void
    {
        // Arrange
        (new Role())->create(['name' => 'super_admin']);

        // Act
        $role = Role::findByName('super_admin');

        // Assert
        $this->assertNotNull($role);
        $this->assertEquals('super_admin', $role->name);
    }

    public function testCanDeleteRole(): void
    {
        // Arrange
        $role = (new Role())->create(['name' => 'temporary_role']);
        $roleId = $role->id;

        // Act
        $role->delete();

        // Assert
        $this->assertDatabaseMissing('roles', ['id' => $roleId, 'name' => 'temporary_role']);
    }
}
