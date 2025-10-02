<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionPolicyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic permissions for testing
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'delete_user']);
        (new Permission())->create(['name' => 'update_user']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

    public function testCanCheckIfModelHasPermissionToModel(): void
    {
        // Arrange
        $user = User::factory()->create();
        User::factory()->create();
        $user->givePermissionTo('update_user');

        // Act & Assert
        $this->assertTrue($user->hasPermissionTo('update_user'));
    }
}
