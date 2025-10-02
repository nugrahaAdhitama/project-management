<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions and roles for testing
        (new Permission())->create(['name' => 'create_user']);
        (new Permission())->create(['name' => 'view_user']);
        (new Permission())->create(['name' => 'view_any_user']);
        (new Permission())->create(['name' => 'update_user']);
        (new Permission())->create(['name' => 'delete_user']);

        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
        $this->superAdminRole->givePermissionTo(['create_user', 'view_user', 'view_any_user', 'update_user', 'delete_user']);

        $this->memberRole = (new Role())->create(['name' => 'member']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCanDeleteAUserUsingMock(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->id = 1;
        $userMock->shouldReceive('delete')->once()->andReturn(true);

        // Act
        $result = $userMock->delete();

        // Assert
        $this->assertTrue($result);
    }

    public function testVerifiesUserIsDeletedFromDatabase(): void
    {
        // Arrange: Create a user
        $user = User::factory()->create(['email' => 'todelete@example.com']);
        $userId = $user->id;

        // Act: Delete user
        $user->delete();

        // Assert: User no longer exists
        $this->assertDatabaseMissing('users', [
            'id' => $userId,
            'email' => 'todelete@example.com',
        ]);

        $this->assertNull((new User())->find($userId));
    }

    public function testAuthorizesUserDeletionViaPolicyWithMock(): void
    {
        // Arrange
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('delete_user');

        $unauthorizedUser = User::factory()->create();

        // Create policy mock
        $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
        $policyMock->shouldReceive('delete')->with($authorizedUser)->andReturn(true);
        $policyMock->shouldReceive('delete')->with($unauthorizedUser)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->delete($authorizedUser));
        $this->assertFalse($policyMock->delete($unauthorizedUser));
    }

    public function testCanForceDeleteAUserUsingMock(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('forceDelete')->once()->andReturn(true);

        // Act
        $result = $userMock->forceDelete();

        // Assert
        $this->assertTrue($result);
    }

    public function testCanDeleteMultipleUsersUsingBulkDelete(): void
    {
        // Arrange: Create multiple users
        $user1 = User::factory()->create(['email' => 'bulk1@example.com']);
        $user2 = User::factory()->create(['email' => 'bulk2@example.com']);
        $user3 = User::factory()->create(['email' => 'bulk3@example.com']);

        $ids = [$user1->id, $user2->id, $user3->id];

        // Act: Delete multiple users
        $deletedCount = User::whereIn('id', $ids)->delete();

        // Assert
        $this->assertEquals(3, $deletedCount);

        // Verify users are deleted
        $this->assertNull((new User())->find($user1->id));
        $this->assertNull((new User())->find($user2->id));
        $this->assertNull((new User())->find($user3->id));
    }
}
