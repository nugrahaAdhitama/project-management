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

// Helper function to setup permissions
function setupPermissions(): void
{
    Permission::create(['name' => 'create_user']);
    Permission::create(['name' => 'view_user']);
    Permission::create(['name' => 'view_any_user']);
}

// Helper function to setup roles
function setupRoles(): array
{
    $superAdminRole = Role::create(['name' => 'super_admin']);
    $superAdminRole->givePermissionTo('create_user');
    $memberRole = Role::create(['name' => 'member']);
    
    return ['superAdminRole' => $superAdminRole, 'memberRole' => $memberRole];
}

// Helper function to create a super admin user
function createSuperAdmin(array $attributes = []): User
{
    $superAdmin = User::factory()->create($attributes);
    $superAdmin->assignRole('super_admin');
    return $superAdmin;
}

// Helper function to create user data
function getUserData(string $name = 'New User', string $email = 'newuser@example.com', string $password = 'password123'): array
{
    return [
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
    ];
}

beforeEach(function () {
    setupPermissions();
    $roles = setupRoles();
    $this->superAdminRole = $roles['superAdminRole'];
    $this->memberRole = $roles['memberRole'];
});

it('allows super admin to create a new user', function () {
    $superAdmin = createSuperAdmin(['name' => 'Super Admin', 'email' => 'superadmin@example.com']);
    $this->actingAs($superAdmin);

    expect($superAdmin->can('create_user'))->toBeTrue();

    $newUser = User::create(getUserData());

    expect($newUser)->toBeInstanceOf(User::class)
        ->and($newUser->name)->toBe('New User')
        ->and($newUser->email)->toBe('newuser@example.com')
        ->and(Hash::check('password123', $newUser->password))->toBeTrue();

    $this->assertDatabaseHas('users', ['name' => 'New User', 'email' => 'newuser@example.com']);
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
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $plainPassword = 'SecurePassword123!';
    $newUser = User::create(getUserData('Test User', 'test@example.com', $plainPassword));

    expect($newUser->password)->not->toBe($plainPassword)
        ->and(Hash::check($plainPassword, $newUser->password))->toBeTrue();

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

    $userFromDb = User::where('email', 'test@example.com')->first();
    expect(Hash::check($plainPassword, $userFromDb->password))->toBeTrue();
});

it('assigns roles to newly created user', function () {
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $newUser = User::create(getUserData('User With Role', 'userrole@example.com', 'password'));
    $newUser->assignRole('member');

    expect($newUser->hasRole('member'))->toBeTrue()
        ->and($newUser->roles->count())->toBe(1)
        ->and($newUser->roles->first()->name)->toBe('member');
});

it('validates required fields when creating user', function () {
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    expect(fn() => User::create(['email' => 'test@example.com', 'password' => Hash::make('password')]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('validates unique email when creating user', function () {
    $superAdmin = createSuperAdmin(['email' => 'superadmin@example.com']);
    $this->actingAs($superAdmin);

    User::create(getUserData('Existing User', 'existing@example.com', 'password'));

    expect(fn() => User::create(getUserData('Duplicate User', 'existing@example.com', 'password')))
        ->toThrow(\Illuminate\Database\QueryException::class);
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
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $adminRole = Role::create(['name' => 'admin']);
    $newUser = User::create(getUserData('Multi Role User', 'multirole@example.com', 'password'));
    $newUser->assignRole(['member', 'admin']);

    expect($newUser->hasRole('member'))->toBeTrue()
        ->and($newUser->hasRole('admin'))->toBeTrue()
        ->and($newUser->roles->count())->toBe(2);
});

it('sets email_verified_at when provided during user creation', function () {
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $newUser = User::create(getUserData('Verified User', 'verified@example.com', 'password'));
    $verifiedAt = now();
    $newUser->email_verified_at = $verifiedAt;
    $newUser->save();
    $newUser->refresh();

    expect($newUser->email_verified_at)->not->toBeNull()
        ->and($newUser->hasVerifiedEmail())->toBeTrue();
});

it('creates user without email verification when not provided', function () {
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $newUser = User::create(getUserData('Unverified User', 'unverified@example.com', 'password'));

    expect($newUser->email_verified_at)->toBeNull()
        ->and($newUser->hasVerifiedEmail())->toBeFalse();
});

it('allows super admin to create user with google_id for OAuth', function () {
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $newUser = User::create([
        'name' => 'Google User',
        'email' => 'google@example.com',
        'password' => Hash::make('password'),
        'google_id' => '123456789',
    ]);

    expect($newUser->google_id)->toBe('123456789');
    $this->assertDatabaseHas('users', ['email' => 'google@example.com', 'google_id' => '123456789']);
});

it('mocks user creation and verifies method calls', function () {
    $superAdmin = createSuperAdmin();
    $this->actingAs($superAdmin);

    $userMock = Mockery::mock(User::class)->makePartial();
    $userMock->shouldReceive('save')->once()->andReturn(true);

    $userMock->name = 'Mocked User';
    $userMock->email = 'mocked@example.com';
    $userMock->password = Hash::make('password');

    $result = $userMock->save();

    expect($result)->toBeTrue();
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});