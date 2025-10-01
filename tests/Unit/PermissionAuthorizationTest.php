<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        Permission::create(['name' => 'view_user']);
        Permission::create(['name' => 'view_any_user']);
        Permission::create(['name' => 'create_user']);
        Permission::create(['name' => 'update_user']);
        Permission::create(['name' => 'delete_user']);
        Permission::create(['name' => 'delete_any_user']);

        Permission::create(['name' => 'view_project']);
        Permission::create(['name' => 'view_any_project']);
        Permission::create(['name' => 'create_project']);
        Permission::create(['name' => 'update_project']);
        Permission::create(['name' => 'delete_project']);

        Permission::create(['name' => 'view_ticket']);
        Permission::create(['name' => 'create_ticket']);
        Permission::create(['name' => 'update_ticket']);
        Permission::create(['name' => 'delete_ticket']);

        // Create roles
        $this->superAdminRole = Role::create(['name' => 'super_admin']);
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->memberRole = Role::create(['name' => 'member']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================
    // PERMISSION CREATION & MANAGEMENT TESTS
    // ============================================

    public function testCanCreatePermission(): void
    {
        // Arrange
        $permissionName = 'custom_permission';

        // Act
        $permission = Permission::create(['name' => $permissionName]);

        // Assert
        $this->assertNotNull($permission);
        $this->assertEquals($permissionName, $permission->name);
        $this->assertDatabaseHas('permissions', ['name' => $permissionName]);
    }

    public function testCanRetrieveAllPermissions(): void
    {
        // Act
        $permissions = Permission::all();

        // Assert: Should have at least the permissions created in setUp
        $this->assertGreaterThanOrEqual(15, $permissions->count());
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
        $permission = Permission::create(['name' => 'temporary_permission']);
        $permissionId = $permission->id;

        // Act
        $permission->delete();

        // Assert
        $this->assertDatabaseMissing('permissions', ['id' => $permissionId]);
    }

    public function testCanGetPermissionsByPrefix(): void
    {
        // Act
        $viewPermissions = Permission::where('name', 'like', 'view%')->get();
        $createPermissions = Permission::where('name', 'like', 'create%')->get();

        // Assert
        $this->assertGreaterThan(0, $viewPermissions->count());
        $this->assertGreaterThan(0, $createPermissions->count());
    }

    // ============================================
    // DIRECT PERMISSION ASSIGNMENT TESTS
    // ============================================

    public function testCanAssignDirectPermissionToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->givePermissionTo('view_user');

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertDatabaseHas('model_has_permissions', [
            'model_id' => $user->id,
            'model_type' => get_class($user),
        ]);
    }

    public function testCanAssignMultipleDirectPermissionsToUser(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $user->givePermissionTo(['view_user', 'create_user', 'update_user']);

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('create_user'));
        $this->assertTrue($user->hasPermissionTo('update_user'));
        $this->assertCount(3, $user->permissions);
    }

    public function testCanAssignPermissionUsingPermissionObject(): void
    {
        // Arrange
        $user = User::factory()->create();
        $permission = Permission::findByName('view_user');

        // Act
        $user->givePermissionTo($permission);

        // Assert
        $this->assertTrue($user->hasPermissionTo('view_user'));
    }

    public function testCannotAssignDuplicateDirectPermission(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');

        // Act: Try to assign same permission again
        $user->givePermissionTo('view_user');

        // Assert: Should still have only 1 direct permission
        $this->assertCount(1, $user->permissions);
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

    // ============================================
    // PERMISSION POLICY INTEGRATION TESTS
    // ============================================

    public function testPolicyUsesPermissionChecking(): void
    {
        // Arrange
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('create_user');

        $unauthorizedUser = User::factory()->create();

        // Create policy mock
        $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
        $policyMock->shouldReceive('create')->with($authorizedUser)->andReturn(true);
        $policyMock->shouldReceive('create')->with($unauthorizedUser)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->create($authorizedUser));
        $this->assertFalse($policyMock->create($unauthorizedUser));
    }

    public function testCanAuthorizeActionViaGateAndPermission(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('delete_user');

        // Act
        $canDelete = Gate::forUser($user)->allows('delete_user');

        // Assert
        $this->assertTrue($canDelete);
    }

    // ============================================
    // FILAMENT SHIELD INTEGRATION TESTS
    // ============================================

    public function testSuperAdminHasAllPermissions(): void
    {
        // Arrange
        $superAdmin = User::factory()->create();
        $this->superAdminRole->givePermissionTo(Permission::all());
        $superAdmin->assignRole('super_admin');

        // Act & Assert: Super admin should have all permissions
        $this->assertTrue($superAdmin->hasPermissionTo('view_user'));
        $this->assertTrue($superAdmin->hasPermissionTo('create_user'));
        $this->assertTrue($superAdmin->hasPermissionTo('delete_user'));
        $this->assertTrue($superAdmin->hasPermissionTo('view_project'));
        $this->assertTrue($superAdmin->hasPermissionTo('create_project'));
        $this->assertTrue($superAdmin->hasPermissionTo('delete_project'));
    }

    public function testFilamentResourcePermissions(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act: Give Filament Shield standard permissions for a resource
        $user->givePermissionTo([
            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
        ]);

        // Assert: User has all CRUD permissions for user resource
        $this->assertTrue($user->hasPermissionTo('view_user'));
        $this->assertTrue($user->hasPermissionTo('view_any_user'));
        $this->assertTrue($user->hasPermissionTo('create_user'));
        $this->assertTrue($user->hasPermissionTo('update_user'));
        $this->assertTrue($user->hasPermissionTo('delete_user'));
        $this->assertTrue($user->hasPermissionTo('delete_any_user'));
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
        $firstCheck = $user->hasPermissionTo('view_user');

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

    public function testCanCheckPermissionWithGuard(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('view_user');

        // Act & Assert: Check permission with default guard
        $this->assertTrue($user->hasPermissionTo('view_user', 'web'));
    }

    // ============================================
    // BULK PERMISSION OPERATIONS TESTS
    // ============================================

    public function testCanAssignPermissionsToMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(3)->create();

        // Act: Assign same permissions to all users
        foreach ($users as $user) {
            $user->givePermissionTo(['view_user', 'view_project']);
        }

        // Assert
        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->hasPermissionTo('view_user'));
            $this->assertTrue($user->fresh()->hasPermissionTo('view_project'));
        }
    }

    public function testCanGetUsersWithSpecificPermission(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create();
        $userWithPermission->givePermissionTo('create_user');

        $anotherUserWithPermission = User::factory()->create();
        $anotherUserWithPermission->givePermissionTo('create_user');

        $userWithoutPermission = User::factory()->create();

        // Act
        $usersWithCreatePermission = User::permission('create_user')->get();

        // Assert
        $this->assertGreaterThanOrEqual(2, $usersWithCreatePermission->count());
        $this->assertTrue($usersWithCreatePermission->contains($userWithPermission));
        $this->assertTrue($usersWithCreatePermission->contains($anotherUserWithPermission));
        $this->assertFalse($usersWithCreatePermission->contains($userWithoutPermission));
    }

    public function testCanCheckIfModelHasPermissionToModel(): void
    {
        // Arrange
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $user->givePermissionTo('update_user');

        // Act & Assert
        $this->assertTrue($user->hasPermissionTo('update_user'));
    }
}
