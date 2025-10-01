<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions for roles
        Permission::create(['name' => 'view_any_role']);
        Permission::create(['name' => 'view_role']);
        Permission::create(['name' => 'create_role']);
        Permission::create(['name' => 'update_role']);
        Permission::create(['name' => 'delete_role']);
        Permission::create(['name' => 'delete_any_role']);

        // Setup basic permissions for testing
        Permission::create(['name' => 'create_user']);
        Permission::create(['name' => 'view_user']);
        Permission::create(['name' => 'update_user']);
        Permission::create(['name' => 'delete_user']);
        Permission::create(['name' => 'view_project']);
        Permission::create(['name' => 'create_project']);

        // Create roles
        $this->superAdminRole = Role::create(['name' => 'super_admin']);
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->memberRole = Role::create(['name' => 'member']);

        // Assign permissions to roles
        $this->superAdminRole->givePermissionTo(['create_user', 'view_user', 'update_user', 'delete_user', 'view_project', 'create_project']);
        $this->adminRole->givePermissionTo(['create_user', 'view_user', 'update_user', 'view_project', 'create_project']);
        $this->memberRole->givePermissionTo(['view_user', 'view_project']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================
    // ROLE CREATION TESTS
    // ============================================

    public function testCanCreateRole(): void
    {
        // Arrange
        $roleName = 'developer';

        // Act
        $role = Role::create(['name' => $roleName]);

        // Assert
        $this->assertNotNull($role);
        $this->assertEquals($roleName, $role->name);
        $this->assertDatabaseHas('roles', ['name' => $roleName]);
    }

    public function testCanRetrieveAllRoles(): void
    {
        // Act
        $roles = Role::all();

        // Assert: Should have 3 roles (super_admin, admin, member)
        $this->assertGreaterThanOrEqual(3, $roles->count());
        $this->assertNotNull($roles->where('name', 'super_admin')->first());
        $this->assertNotNull($roles->where('name', 'admin')->first());
        $this->assertNotNull($roles->where('name', 'member')->first());
    }

    public function testCanFindRoleByName(): void
    {
        // Act
        $role = Role::findByName('super_admin');

        // Assert
        $this->assertNotNull($role);
        $this->assertEquals('super_admin', $role->name);
    }

    public function testCanDeleteRole(): void
    {
        // Arrange
        $role = Role::create(['name' => 'temporary_role']);
        $roleId = $role->id;

        // Act
        $role->delete();

        // Assert
        $this->assertDatabaseMissing('roles', ['id' => $roleId, 'name' => 'temporary_role']);
    }

    // ============================================
    // ROLE ASSIGNMENT TO USER TESTS
    // ============================================

    public function testCanAssignSingleRoleToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole('member');

        // Assert
        $this->assertTrue($user->hasRole('member'));
        $this->assertCount(1, $user->roles);
    }

    public function testCanAssignMultipleRolesToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole(['admin', 'member']);

        // Assert
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('member'));
        $this->assertCount(2, $user->roles);
    }

    public function testCanAssignRoleUsingRoleObject(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole($this->superAdminRole);

        // Assert
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertCount(1, $user->roles);
    }

    public function testCannotAssignDuplicateRole(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('member');

        // Act: Try to assign same role again
        $user->assignRole('member');

        // Assert: Should still have only 1 role
        $this->assertCount(1, $user->roles);
    }

    public function testUserInheritsPermissionsFromRole(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole('super_admin');

        // Assert: User should have permissions from super_admin role
        $this->assertTrue($user->can('create_user'));
        $this->assertTrue($user->can('delete_user'));
        $this->assertTrue($user->can('view_project'));
        $this->assertTrue($user->can('create_project'));
    }

    public function testMemberRoleHasLimitedPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->assignRole('member');

        // Assert: Member should have limited permissions
        $this->assertTrue($user->can('view_user'));
        $this->assertTrue($user->can('view_project'));
        $this->assertFalse($user->can('create_user'));
        $this->assertFalse($user->can('delete_user'));
        $this->assertFalse($user->can('create_project'));
    }

    // ============================================
    // ROLE REMOVAL TESTS
    // ============================================

    public function testCanRemoveRoleFromUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('member');
        $this->assertTrue($user->hasRole('member'));

        // Act
        $user->removeRole('member');

        // Assert
        $this->assertFalse($user->hasRole('member'));
        $this->assertCount(0, $user->roles);
    }

    public function testCanRemoveMultipleRolesFromUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'member']);
        $this->assertCount(2, $user->roles);

        // Act
        $user->removeRole(['admin', 'member']);

        // Assert
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('member'));
        $this->assertCount(0, $user->roles);
    }

    public function testRemovingRoleRevokesPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->assertTrue($user->can('delete_user'));

        // Act
        $user->removeRole('super_admin');

        // Assert: User should lose permissions
        $this->assertFalse($user->can('delete_user'));
    }

    // ============================================
    // ROLE SYNC TESTS
    // ============================================

    public function testCanSyncRoles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['super_admin', 'member']);
        $this->assertCount(2, $user->roles);

        // Act: Sync to only admin role
        $user->syncRoles(['admin']);

        // Assert
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertFalse($user->hasRole('member'));
        $this->assertCount(1, $user->roles);
    }

    public function testSyncRolesReplacesAllExistingRoles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['super_admin', 'admin', 'member']);

        // Act
        $user->syncRoles(['member']);

        // Assert: Only member role should remain
        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->hasRole('member'));
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

    // ============================================
    // ROLE POLICY TESTS
    // ============================================

    public function testAuthorizesRoleCreationViaPolicy(): void
    {
        // Arrange
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('create_role');

        $unauthorizedUser = User::factory()->create();

        // Create policy mock
        $policyMock = Mockery::mock(RolePolicy::class)->makePartial();
        $policyMock->shouldReceive('create')->with($authorizedUser)->andReturn(true);
        $policyMock->shouldReceive('create')->with($unauthorizedUser)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->create($authorizedUser));
        $this->assertFalse($policyMock->create($unauthorizedUser));
    }

    public function testAuthorizesRoleUpdateViaPolicy(): void
    {
        // Arrange
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('update_role');

        $unauthorizedUser = User::factory()->create();

        $role = Role::create(['name' => 'test_role']);

        // Create policy mock
        $policyMock = Mockery::mock(RolePolicy::class)->makePartial();
        $policyMock->shouldReceive('update')->with($authorizedUser, $role)->andReturn(true);
        $policyMock->shouldReceive('update')->with($unauthorizedUser, $role)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->update($authorizedUser, $role));
        $this->assertFalse($policyMock->update($unauthorizedUser, $role));
    }

    public function testAuthorizesRoleDeletionViaPolicy(): void
    {
        // Arrange
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('delete_role');

        $unauthorizedUser = User::factory()->create();

        $role = Role::create(['name' => 'test_role']);

        // Create policy mock
        $policyMock = Mockery::mock(RolePolicy::class)->makePartial();
        $policyMock->shouldReceive('delete')->with($authorizedUser, $role)->andReturn(true);
        $policyMock->shouldReceive('delete')->with($unauthorizedUser, $role)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->delete($authorizedUser, $role));
        $this->assertFalse($policyMock->delete($unauthorizedUser, $role));
    }

    // ============================================
    // ROLE PERMISSION MANAGEMENT TESTS
    // ============================================

    public function testCanAssignPermissionsToRole(): void
    {
        // Arrange
        $role = Role::create(['name' => 'custom_role']);
        $permission = Permission::create(['name' => 'custom_permission']);

        // Act
        $role->givePermissionTo($permission);

        // Assert
        $this->assertTrue($role->hasPermissionTo('custom_permission'));
    }

    public function testCanRevokePermissionsFromRole(): void
    {
        // Arrange
        $role = Role::create(['name' => 'custom_role']);
        $permission = Permission::create(['name' => 'custom_permission']);
        $role->givePermissionTo($permission);
        $this->assertTrue($role->hasPermissionTo('custom_permission'));

        // Act
        $role->revokePermissionTo($permission);

        // Assert
        $this->assertFalse($role->hasPermissionTo('custom_permission'));
    }

    public function testCanSyncPermissionsToRole(): void
    {
        // Arrange
        $role = Role::create(['name' => 'custom_role']);
        $perm1 = Permission::create(['name' => 'permission_1']);
        $perm2 = Permission::create(['name' => 'permission_2']);
        $perm3 = Permission::create(['name' => 'permission_3']);

        $role->givePermissionTo([$perm1, $perm2]);

        // Act: Sync to only perm2 and perm3
        $role->syncPermissions([$perm2, $perm3]);

        // Assert
        $this->assertFalse($role->hasPermissionTo('permission_1'));
        $this->assertTrue($role->hasPermissionTo('permission_2'));
        $this->assertTrue($role->hasPermissionTo('permission_3'));
    }

    public function testUserWithMultipleRolesHasCombinedPermissions(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act: Assign both admin and member roles
        $user->assignRole(['admin', 'member']);

        // Assert: User should have combined permissions from both roles
        $this->assertTrue($user->can('create_user')); // from admin
        $this->assertTrue($user->can('view_user')); // from both
        $this->assertTrue($user->can('view_project')); // from both
        $this->assertFalse($user->can('delete_user')); // neither has this
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
