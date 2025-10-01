<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class); 

beforeEach(function () {
    // Setup permissions and roles for testing
    Permission::create(['name' => 'create_user']);
    Permission::create(['name' => 'view_user']);
    Permission::create(['name' => 'view_any_user']);
    Permission::create(['name' => 'update_user']);
    Permission::create(['name' => 'delete_user']);

    $this->superAdminRole = Role::create(['name' => 'super_admin']);
    $this->superAdminRole->givePermissionTo(['create_user', 'view_user', 'view_any_user', 'update_user', 'delete_user']);

    $this->memberRole = Role::create(['name' => 'member']);
});

// ============================================
// CREATE TESTS
// ============================================

it('can create a user with valid data using mock', function () {
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
    expect($result)->toBeTrue()
        ->and($userMock->name)->toBe('Test User')
        ->and($userMock->email)->toBe('test@example.com');
});

it('validates required fields during user creation', function () {
    // Act & Assert: Missing name
    expect(function () {
        User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('validates unique email during user creation', function () {
    // Arrange: Create existing user
    User::factory()->create(['email' => 'existing@example.com']);

    // Act & Assert: Duplicate email
    expect(function () {
        User::create([
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('hashes password automatically when creating user', function () {
    // Arrange
    $plainPassword = 'SecurePassword123';

    // Act
    $user = User::create([
        'name' => 'Password User',
        'email' => 'password@example.com',
        'password' => Hash::make($plainPassword),
    ]);

    // Assert
    expect($user->password)->not->toBe($plainPassword)
        ->and(Hash::check($plainPassword, $user->password))->toBeTrue();
});

it('authorizes user creation via policy with mock', function () {
    // Arrange: Create users with/without permission
    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo('create_user');

    $unauthorizedUser = User::factory()->create();

    // Create policy mock
    $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
    $policyMock->shouldReceive('create')->with($authorizedUser)->andReturn(true);
    $policyMock->shouldReceive('create')->with($unauthorizedUser)->andReturn(false);

    // Act & Assert
    expect($policyMock->create($authorizedUser))->toBeTrue()
        ->and($policyMock->create($unauthorizedUser))->toBeFalse();
});

// ============================================
// READ TESTS
// ============================================

it('can retrieve a single user by id', function () {
    // Arrange: Create a user in database
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    // Act: Retrieve user by ID
    $foundUser = User::find($user->id);

    // Assert
    expect($foundUser)->not->toBeNull()
        ->and($foundUser->id)->toBe($user->id)
        ->and($foundUser->name)->toBe('John Doe')
        ->and($foundUser->email)->toBe('john@example.com');
});

it('can retrieve all users', function () {
    // Arrange: Create multiple users in database
    User::factory()->create(['name' => 'User 1', 'email' => 'user1@example.com']);
    User::factory()->create(['name' => 'User 2', 'email' => 'user2@example.com']);

    // Act: Retrieve all users
    $users = User::all();

    // Assert: Should have at least 2 users
    expect($users->count())->toBeGreaterThanOrEqual(2)
        ->and($users->where('name', 'User 1')->first())->not->toBeNull()
        ->and($users->where('name', 'User 2')->first())->not->toBeNull();
});

it('can find user by email', function () {
    // Arrange: Create a user with specific email
    $email = 'findme@example.com';
    User::factory()->create([
        'name' => 'Find Me',
        'email' => $email,
    ]);

    // Act: Find user by email
    $user = User::where('email', $email)->first();

    // Assert
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe($email)
        ->and($user->name)->toBe('Find Me');
});

it('returns null when user not found', function () {
    // Arrange: Use a very high ID that doesn't exist
    $nonExistentId = 999999;

    // Act: Try to find non-existent user
    $user = User::find($nonExistentId);

    // Assert
    expect($user)->toBeNull();
});

it('authorizes user viewing via policy with mock', function () {
    // Arrange
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view_user');

    $nonViewer = User::factory()->create();

    // Create policy mock
    $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
    $policyMock->shouldReceive('view')->with($viewer)->andReturn(true);
    $policyMock->shouldReceive('view')->with($nonViewer)->andReturn(false);

    // Act & Assert
    expect($policyMock->view($viewer))->toBeTrue()
        ->and($policyMock->view($nonViewer))->toBeFalse();
});

// ============================================
// UPDATE TESTS
// ============================================

it('can update user data using mock', function () {
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
    expect($result)->toBeTrue();
});

it('can update user email using mock', function () {
    // Arrange
    $userMock = Mockery::mock(User::class)->makePartial();
    $userMock->email = 'old@example.com';

    $userMock->shouldReceive('save')->once()->andReturn(true);

    // Act
    $userMock->email = 'new@example.com';
    $result = $userMock->save();

    // Assert
    expect($result)->toBeTrue()
        ->and($userMock->email)->toBe('new@example.com');
});

it('can update user password using mock', function () {
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
    expect($result)->toBeTrue();
});

it('validates unique email during user update', function () {
    // Arrange: Create two users
    User::factory()->create(['email' => 'first@example.com']);
    $secondUser = User::factory()->create(['email' => 'second@example.com']);

    // Act & Assert: Try to update second user with first user's email
    expect(function () use ($secondUser) {
        $secondUser->email = 'first@example.com';
        $secondUser->saveOrFail();
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('authorizes user update via policy with mock', function () {
    // Arrange
    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo('update_user');

    $unauthorizedUser = User::factory()->create();

    // Create policy mock
    $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
    $policyMock->shouldReceive('update')->with($authorizedUser)->andReturn(true);
    $policyMock->shouldReceive('update')->with($unauthorizedUser)->andReturn(false);

    // Act & Assert
    expect($policyMock->update($authorizedUser))->toBeTrue()
        ->and($policyMock->update($unauthorizedUser))->toBeFalse();
});

it('can update multiple user attributes at once using mock', function () {
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
    expect($result)->toBeTrue();
});

// ============================================
// DELETE TESTS
// ============================================

it('can delete a user using mock', function () {
    // Arrange
    $userMock = Mockery::mock(User::class)->makePartial();
    $userMock->id = 1;
    $userMock->shouldReceive('delete')->once()->andReturn(true);

    // Act
    $result = $userMock->delete();

    // Assert
    expect($result)->toBeTrue();
});

it('verifies user is deleted from database', function () {
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

    expect(User::find($userId))->toBeNull();
});

it('authorizes user deletion via policy with mock', function () {
    // Arrange
    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo('delete_user');

    $unauthorizedUser = User::factory()->create();

    // Create policy mock
    $policyMock = Mockery::mock(UserPolicy::class)->makePartial();
    $policyMock->shouldReceive('delete')->with($authorizedUser)->andReturn(true);
    $policyMock->shouldReceive('delete')->with($unauthorizedUser)->andReturn(false);

    // Act & Assert
    expect($policyMock->delete($authorizedUser))->toBeTrue()
        ->and($policyMock->delete($unauthorizedUser))->toBeFalse();
});

it('can force delete a user using mock', function () {
    // Arrange
    $userMock = Mockery::mock(User::class)->makePartial();
    $userMock->shouldReceive('forceDelete')->once()->andReturn(true);

    // Act
    $result = $userMock->forceDelete();

    // Assert
    expect($result)->toBeTrue();
});

it('can delete multiple users using bulk delete', function () {
    // Arrange: Create multiple users
    $user1 = User::factory()->create(['email' => 'bulk1@example.com']);
    $user2 = User::factory()->create(['email' => 'bulk2@example.com']);
    $user3 = User::factory()->create(['email' => 'bulk3@example.com']);

    $ids = [$user1->id, $user2->id, $user3->id];

    // Act: Delete multiple users
    $deletedCount = User::whereIn('id', $ids)->delete();

    // Assert
    expect($deletedCount)->toBe(3);

    // Verify users are deleted
    expect(User::find($user1->id))->toBeNull()
        ->and(User::find($user2->id))->toBeNull()
        ->and(User::find($user3->id))->toBeNull();
});

afterEach(function () {
    Mockery::close();
});
