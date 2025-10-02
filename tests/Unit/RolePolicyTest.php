<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions for roles
        (new Permission())->create(['name' => 'create_role']);
        (new Permission())->create(['name' => 'update_role']);
        (new Permission())->create(['name' => 'delete_role']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

        $role = (new Role())->create(['name' => 'test_role']);

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

        $role = (new Role())->create(['name' => 'test_role']);

        // Create policy mock
        $policyMock = Mockery::mock(RolePolicy::class)->makePartial();
        $policyMock->shouldReceive('delete')->with($authorizedUser, $role)->andReturn(true);
        $policyMock->shouldReceive('delete')->with($unauthorizedUser, $role)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->delete($authorizedUser, $role));
        $this->assertFalse($policyMock->delete($unauthorizedUser, $role));
    }
}
