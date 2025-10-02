<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleEdgeCasesValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
    }

    // ============================================
    // EDGE CASES AND VALIDATION TESTS
    // ============================================

    public function testCannotAssignNonExistentRole(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert
        $this->expectException(\Spatie\Permission\Exceptions\RoleDoesNotExist::class);
        $user->assignRole('non_existent_role');
    }

    public function testGetRoleNamesReturnsCorrectRoles(): void
    {
        // Arrange
        (new Role())->create(['name' => 'admin']);
        (new Role())->create(['name' => 'member']);
        $user = User::factory()->create();
        $user->assignRole(['admin', 'member']);

        // Act
        $roleNames = $user->getRoleNames();

        // Assert
        $this->assertCount(2, $roleNames);
        $this->assertTrue($roleNames->contains('admin'));
        $this->assertTrue($roleNames->contains('member'));
    }

    public function testRoleRelationshipWorksCorrectly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        // Act
        $roles = $user->roles;

        // Assert
        $this->assertNotNull($roles);
        $this->assertCount(1, $roles);
        $this->assertEquals('super_admin', $roles->first()->name);
    }
}
