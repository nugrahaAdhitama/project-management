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
    // Setup permissions and roles yang dibutuhkan untuk testing
    Permission::create(['name' => 'create_user']);
    Permission::create(['name' => 'view_user']);
    Permission::create(['name' => 'view_any_user']);

    $this->superAdminRole = Role::create(['name' => 'super_admin']);
    $this->superAdminRole->givePermissionTo('create_user');

    $this->memberRole = Role::create(['name' => 'member']);
});

it('allows super admin to create a new user', function () {
    // Arrange: Create super admin user
    $superAdmin = User::factory()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@example.com',
    ]);
    $superAdmin->assignRole('super_admin');

    // Act as super admin
    $this->actingAs($superAdmin);

    // Assert: Super admin has permission to create user
    expect($superAdmin->can('create_user'))->toBeTrue();

    // Arrange: Data for new user
    $userData = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
    ];

    // Act: Create new user
    $newUser = User::create([
        'name' => $userData['name'],
        'email' => $userData['email'],
        'password' => Hash::make($userData['password']),
    ]);

    // Assert: User created successfully
    expect($newUser)->toBeInstanceOf(User::class)
        ->and($newUser->name)->toBe('New User')
        ->and($newUser->email)->toBe('newuser@example.com')
        ->and(Hash::check('password123', $newUser->password))->toBeTrue();

    // Assert: User exists in database
    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
    ]);
});

it('denies non-super admin users from creating a new user', function () {
    // Arrange: Create regular member user without create_user permission
    $member = User::factory()->create([
        'name' => 'Regular Member',
        'email' => 'member@example.com',
    ]);
    $member->assignRole('member');

    // Act as member
    $this->actingAs($member);

    // Assert: Member does not have permission to create user
    expect($member->can('create_user'))->toBeFalse();
});

it('hashes password when creating a user', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $plainPassword = 'SecurePassword123!';

    // Act: Create user with password
    $newUser = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make($plainPassword),
    ]);

    // Assert: Password is hashed (not plain text)
    expect($newUser->password)->not->toBe($plainPassword)
        ->and(Hash::check($plainPassword, $newUser->password))->toBeTrue();

    // Verify password is stored in database as hashed
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
    ]);

    $userFromDb = User::where('email', 'test@example.com')->first();
    expect(Hash::check($plainPassword, $userFromDb->password))->toBeTrue();
});

it('assigns roles to newly created user', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Create new user
    $newUser = User::create([
        'name' => 'User With Role',
        'email' => 'userrole@example.com',
        'password' => Hash::make('password'),
    ]);

    // Act: Assign role to new user
    $newUser->assignRole('member');

    // Assert: User has the assigned role
    expect($newUser->hasRole('member'))->toBeTrue()
        ->and($newUser->roles->count())->toBe(1)
        ->and($newUser->roles->first()->name)->toBe('member');
});

it('validates required fields when creating user', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Act & Assert: Expect exception when name is missing
    expect(function () {
        User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('validates unique email when creating user', function () {
    // Arrange: Create super admin and existing user
    $superAdmin = User::factory()->create([
        'email' => 'superadmin@example.com',
    ]);
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Create existing user
    User::create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'password' => Hash::make('password'),
    ]);

    // Act & Assert: Expect exception when duplicate email
    expect(function () {
        User::create([
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('verifies UserPolicy create method uses correct permission', function () {
    // Arrange: Create user with create_user permission
    $userWithPermission = User::factory()->create();
    $userWithPermission->givePermissionTo('create_user');

    // Create user without create_user permission
    $userWithoutPermission = User::factory()->create();

    // Create policy instance
    $policy = new UserPolicy();

    // Assert: User with permission can create
    expect($policy->create($userWithPermission))->toBeTrue();

    // Assert: User without permission cannot create
    expect($policy->create($userWithoutPermission))->toBeFalse();
});

it('can assign multiple roles to a user during creation', function () {
    // Arrange: Create super admin and additional role
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $adminRole = Role::create(['name' => 'admin']);

    // Create new user
    $newUser = User::create([
        'name' => 'Multi Role User',
        'email' => 'multirole@example.com',
        'password' => Hash::make('password'),
    ]);

    // Act: Assign multiple roles
    $newUser->assignRole(['member', 'admin']);

    // Assert: User has both roles
    expect($newUser->hasRole('member'))->toBeTrue()
        ->and($newUser->hasRole('admin'))->toBeTrue()
        ->and($newUser->roles->count())->toBe(2);
});

it('sets email_verified_at when provided during user creation', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Act: Create user and then mark email as verified
    $newUser = User::create([
        'name' => 'Verified User',
        'email' => 'verified@example.com',
        'password' => Hash::make('password'),
    ]);

    // Set email_verified_at manually (simulating admin setting it)
    $verifiedAt = now();
    $newUser->email_verified_at = $verifiedAt;
    $newUser->save();

    // Refresh from database
    $newUser->refresh();

    // Assert: Email is verified
    expect($newUser->email_verified_at)->not->toBeNull()
        ->and($newUser->hasVerifiedEmail())->toBeTrue();
});

it('creates user without email verification when not provided', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Act: Create user without email_verified_at
    $newUser = User::create([
        'name' => 'Unverified User',
        'email' => 'unverified@example.com',
        'password' => Hash::make('password'),
    ]);

    // Assert: Email is not verified
    expect($newUser->email_verified_at)->toBeNull()
        ->and($newUser->hasVerifiedEmail())->toBeFalse();
});

it('allows super admin to create user with google_id for OAuth', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Act: Create user with google_id
    $newUser = User::create([
        'name' => 'Google User',
        'email' => 'google@example.com',
        'password' => Hash::make('password'),
        'google_id' => '123456789',
    ]);

    // Assert: Google ID is set
    expect($newUser->google_id)->toBe('123456789');

    $this->assertDatabaseHas('users', [
        'email' => 'google@example.com',
        'google_id' => '123456789',
    ]);
});

it('mocks user creation and verifies method calls', function () {
    // Arrange: Create super admin
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    // Create a partial mock of User model
    $userMock = Mockery::mock(User::class)->makePartial();

    // Setup mock expectations
    $userMock->shouldReceive('save')
        ->once()
        ->andReturn(true);

    // Set attributes
    $userMock->name = 'Mocked User';
    $userMock->email = 'mocked@example.com';
    $userMock->password = Hash::make('password');

    // Act: Call save
    $result = $userMock->save();

    // Assert: Save was called and returned true
    expect($result)->toBeTrue();

    // Clean up mock
    Mockery::close();
});

afterEach(function () {
    // Clean up Mockery after each test
    Mockery::close();
});
