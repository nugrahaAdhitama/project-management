<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionFilamentShieldTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'view_any_user']);
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'update_user']);
        (new Permission())->create(['name' => 'delete_user']);
        (new Permission())->create(['name' => 'delete_any_user']);
        (new Permission())->create(['name' => 'view_project']);
        (new Permission())->create(['name' => 'create_project']);
        (new Permission())->create(['name' => 'delete_project']);

        // Create roles
        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
    }

    // ============================================
    // FILAMENT SHIELD INTEGRATION TESTS
    // ============================================

    public function testSuperAdminHasAllPermissions(): void
    {
        // Arrange
        $superAdmin = User::factory()->create();
        $this->superAdminRole->givePermissionTo((new Permission())->all());
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
}
