<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionRoleInheritanceTest extends TestCase
{
    use RefreshDatabase;

    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'update_user']);
        (new Permission())->create(['name' => 'view_project']);
        (new Permission())->create(['name' => 'view_ticket']);
        (new Permission())->create(['name' => 'create_ticket']);

        // Create roles
        $this->adminRole = (new Role())->create(['name' => 'admin']);
        $this->memberRole = (new Role())->create(['name' => 'member']);
    }

    // ============================================
    // PERMISSION INHERITANCE FROM ROLES TESTS
    // ============================================

    public function testUserInheritsPermissionsFromRole(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user', 'create_user', 'update_user']);
        $user = User::factory()->create();

        // Act
        $user->assignRole('admin');

        // Assert: User should have permissions from admin role
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('create_user'));
        $this->assertTrue($user->hasPermissionTo('update_user'));
    }

    public function testDirectPermissionsAndRolePermissionsCombine(): void
    {
        // Arrange
        $this->memberRole->givePermissionTo(['view_user', 'view_project']);
        $user = User::factory()->create();
        $user->assignRole('member');

        // Act: Give direct permission
        $user->givePermissionTo('create_ticket');

        // Assert: User should have both role permissions and direct permissions
        $this->assertTrue($user->hasPermissionTo('view_user')); // from role
        $this->assertTrue($user->hasPermissionTo('view_project')); // from role
        $this->assertTrue($user->hasPermissionTo('create_ticket')); // direct
    }

    public function testMultipleRolePermissionsCombine(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user', 'create_user']);
        $this->memberRole->givePermissionTo(['view_project', 'view_ticket']);
        $user = User::factory()->create();

        // Act: Assign multiple roles
        $user->assignRole(['admin', 'member']);

        // Assert: User should have combined permissions from both roles
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('create_user'));
        $this->assertTrue($user->hasPermissionTo('view_project'));
        $this->assertTrue($user->hasPermissionTo('view_ticket'));
    }

    public function testRemovingRoleRemovesItsPermissions(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user', 'create_user']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->assertTrue($user->hasPermissionTo('create_user'));

        // Act
        $user->removeRole('admin');

        // Assert: User should lose role permissions
        $this->assertFalse($user->hasPermissionTo('create_user'));
    }

    public function testDirectPermissionsRemainAfterRoleRemoval(): void
    {
        // Arrange
        $this->adminRole->givePermissionTo(['view_user']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo('create_ticket'); // direct permission

        // Act
        $user->removeRole('admin');

        // Assert: Direct permission should remain
        $this->assertFalse($user->hasPermissionTo('view_user')); // role permission lost
        $this->assertTrue($user->hasPermissionTo('create_ticket')); // direct permission kept
    }
}
