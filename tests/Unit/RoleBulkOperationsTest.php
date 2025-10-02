<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
        $this->adminRole = (new Role())->create(['name' => 'admin']);
        $this->memberRole = (new Role())->create(['name' => 'member']);
    }

    // ============================================
    // BULK ROLE OPERATIONS TESTS
    // ============================================

    public function testCanAssignRoleToMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(3)->create();

        // Act: Assign member role to all users
        foreach ($users as $user) {
            $user->assignRole('member');
        }

        // Assert
        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->hasRole('member'));
        }
    }

    public function testCanGetAllUsersWithSpecificRole(): void
    {
        // Arrange: Create users with different roles
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $admin1 = User::factory()->create();
        $admin1->assignRole('admin');

        $admin2 = User::factory()->create();
        $admin2->assignRole('admin');

        $member = User::factory()->create();
        $member->assignRole('member');

        // Act: Get all users with admin role
        $admins = User::role('admin')->get();

        // Assert
        $this->assertCount(2, $admins);
        $this->assertTrue($admins->contains($admin1));
        $this->assertTrue($admins->contains($admin2));
        $this->assertFalse($admins->contains($superAdmin));
        $this->assertFalse($admins->contains($member));
    }

    public function testCanCheckIfUserHasAnyRole(): void
    {
        // Arrange
        $userWithRoles = User::factory()->create();
        $userWithRoles->assignRole(['admin', 'member']);

        $userWithoutRoles = User::factory()->create();

        // Act & Assert
        $this->assertTrue($userWithRoles->hasAnyRole(['admin', 'super_admin']));
        $this->assertFalse($userWithRoles->hasAnyRole(['super_admin']));
        $this->assertFalse($userWithoutRoles->hasAnyRole(['admin', 'member']));
    }

    public function testCanCheckIfUserHasAllRoles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'member']);

        // Act & Assert
        $this->assertTrue($user->hasAllRoles(['admin', 'member']));
        $this->assertFalse($user->hasAllRoles(['admin', 'member', 'super_admin']));
    }
}
