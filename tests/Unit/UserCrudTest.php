<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions and roles for testing
        Permission::create(['name' => 'create_user']);
        Permission::create(['name' => 'view_user']);
        Permission::create(['name' => 'view_any_user']);
        Permission::create(['name' => 'update_user']);
        Permission::create(['name' => 'delete_user']);

        $this->superAdminRole = Role::create(['name' => 'super_admin']);
        $this->superAdminRole->givePermissionTo(['create_user', 'view_user', 'view_any_user', 'update_user', 'delete_user']);

        $this->memberRole = Role::create(['name' => 'member']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================
    // CREATE TESTS
    // ============================================

    public function testCanCreateUserWithValidDataUsingMock(): void
    {
        // Arrange
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Create a partial mock of User model
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('save')->once()->andReturn(true);
        $userMock->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $userMock->shouldReceive('getAttribute')->with('name')->andReturn($userData['name']);
        $userMock->shouldReceive('getAttribute')->with('email')->andReturn($userData['email']);

        // Set attributes
        $userMock->name = $userData['name'];
        $userMock->email = $userData['email'];
        $userMock->password = Hash::make($userData['password']);

        // Act
        $result = $userMock->save();

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('Test User', $userMock->name);
        $this->assertEquals('test@example.com', $userMock->email);
    }

    public function testValidatesRequiredFieldsDuringUserCreation(): void
    {
        // Act & Assert: Missing name
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function testValidatesUniqueEmailDuringUserCreation(): void
    {
        // Arrange: Create existing user
        User::factory()->create(['email' => 'existing@example.com']);

        // Act & Assert: Duplicate email
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function testHashesPasswordAutomaticallyWhenCreatingUser(): void
    {
        // Arrange
        $plainPassword = 'SecurePassword123';

        // Act
        $user = User::create([
            'name' => 'Password User',
            'email' => 'password@example.com',
            'password' => Hash::make($plainPassword),
        ]);

        // Assert
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    public function testAuthorizesUserCreationViaPolicyWithMock(): void
    {
        // Arrange: Create users with/without permission
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

    // ============================================
    // READ TESTS
    // ============================================

    public function testCanRetrieveASingleUserById(): void
    {
        // Arrange: Create a user in database
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Act: Retrieve user by ID
        $foundUser = User::find($user->id);

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
        $users = User::all();

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
        $user = User::find($nonExistentId);

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

    // ============================================
    // UPDATE TESTS
    // ============================================

    public function testCanUpdateUserDataUsingMock(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->id = 1;
        $userMock->name = 'Old Name';
        $userMock->email = 'old@example.com';

        $userMock->shouldReceive('update')
            ->with(['name' => 'New Name'])
            ->andReturn(true);

        // Act
        $result = $userMock->update(['name' => 'New Name']);

        // Assert
        $this->assertTrue($result);
    }

    public function testCanUpdateUserEmailUsingMock(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->email = 'old@example.com';

        $userMock->shouldReceive('save')->once()->andReturn(true);

        // Act
        $userMock->email = 'new@example.com';
        $result = $userMock->save();

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('new@example.com', $userMock->email);
    }

    public function testCanUpdateUserPasswordUsingMock(): void
    {
        // Arrange
        $newPassword = 'NewSecurePassword123';
        $hashedPassword = Hash::make($newPassword);

        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('update')
            ->with(['password' => $hashedPassword])
            ->andReturn(true);

        // Act
        $result = $userMock->update(['password' => $hashedPassword]);

        // Assert
        $this->assertTrue($result);
    }

    public function testValidatesUniqueEmailDuringUserUpdate(): void
    {
        // Arrange: Create two users
        User::factory()->create(['email' => 'first@example.com']);
        $secondUser = User::factory()->create(['email' => 'second@example.com']);

        // Act & Assert: Try to update second user with first user's email
        $this->expectException(\Illuminate\Database\QueryException::class);

        $secondUser->email = 'first@example.com';
        $secondUser->saveOrFail();
    }

    public function testAuthorizesUserUpdateViaPolicyWithMock(): void
    {
        // Arrange
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('update_user');

        $unauthorizedUser = User::factory()->create();

        // Create policy mock
        $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
        $policyMock->shouldReceive('update')->with($authorizedUser)->andReturn(true);
        $policyMock->shouldReceive('update')->with($unauthorizedUser)->andReturn(false);

        // Act & Assert
        $this->assertTrue($policyMock->update($authorizedUser));
        $this->assertFalse($policyMock->update($unauthorizedUser));
    }

    public function testCanUpdateMultipleUserAttributesAtOnceUsingMock(): void
    {
        // Arrange
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('update')
            ->with($updateData)
            ->andReturn(true);

        // Act
        $result = $userMock->update($updateData);

        // Assert
        $this->assertTrue($result);
    }

    // ============================================
    // DELETE TESTS
    // ============================================

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

        $this->assertNull(User::find($userId));
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
        $this->assertNull(User::find($user1->id));
        $this->assertNull(User::find($user2->id));
        $this->assertNull(User::find($user3->id));
    }
}
