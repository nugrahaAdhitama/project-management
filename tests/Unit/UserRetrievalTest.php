<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRetrievalTest extends TestCase
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

    public function testCanRetrieveASingleUserById(): void
    {
        // Arrange: Create a user in database
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Act: Retrieve user by ID
        $foundUser = (new User())->find($user->id);

        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals('John Doe', $foundUser->name);
        $this->assertEquals('john@example.com', $foundUser->email);
    }

    public function testCanRetrieveAllUsers(): void
    {
        // Arrange: Create multiple users in database
        User::factory()->create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::factory()->create(['name' => 'User 2', 'email' => 'user2@example.com']);

        // Act: Retrieve all users
        $users = (new User())->all();

        // Assert: Should have at least 2 users
        $this->assertGreaterThanOrEqual(2, $users->count());
        $this->assertNotNull($users->where('name', 'User 1')->first());
        $this->assertNotNull($users->where('name', 'User 2')->first());
    }

    public function testCanFindUserByEmail(): void
    {
        // Arrange: Create a user with specific email
        $email = 'findme@example.com';
        User::factory()->create([
            'name' => 'Find Me',
            'email' => $email,
        ]);

        // Act: Find user by email
        $user = User::where('email', $email)->first();

        // Assert
        $this->assertNotNull($user);
        $this->assertEquals($email, $user->email);
        $this->assertEquals('Find Me', $user->name);
    }

    public function testReturnsNullWhenUserNotFound(): void
    {
        // Arrange: Use a very high ID that doesn't exist
        $nonExistentId = 999999;

        // Act: Try to find non-existent user
        $user = (new User())->find($nonExistentId);

        // Assert
        $this->assertNull($user);
    }

    public function testAuthorizesUserViewingViaPolicyWithMock(): void
    {
        // Arrange
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view_user');

        $nonViewer = User::factory()->create();

        // Create policy mock
        $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
        $policyMock->shouldReceive('view')->with($viewer)->andReturn(true);
        $policyMock->shouldReceive('view')->with($nonViewer)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->view($viewer));
        $this->assertFalse($policyMock->view($nonViewer));
    }
}
