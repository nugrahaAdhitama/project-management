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
    // Setup permissions
    $permissionModel = new Permission();
    $permissionModel->create(['name' => 'create_user']);
    $permissionModel->create(['name' => 'view_user']);
    $permissionModel->create(['name' => 'view_any_user']);
    
    // Setup roles
    $roleModel = new Role();
    $this->superAdminRole = $roleModel->create(['name' => 'super_admin']);
    $this->superAdminRole->givePermissionTo('create_user');
    $this->memberRole = $roleModel->create(['name' => 'member']);
});

it('allows super admin to create a new user', function () {
    $superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'superadmin@example.com']);
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    expect($superAdmin->can('create_user'))->toBeTrue();

    $newUser = User::create([
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => Hash::make('password123'),
    ]);

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
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $plainPassword = 'SecurePassword123!';
    $newUser = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make($plainPassword),
    ]);

    expect($newUser->password)->not->toBe($plainPassword)
        ->and(Hash::check($plainPassword, $newUser->password))->toBeTrue();

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

    $userFromDb = User::where('email', 'test@example.com')->first();
    expect(Hash::check($plainPassword, $userFromDb->password))->toBeTrue();
});

it('assigns roles to newly created user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $newUser = User::create([
        'name' => 'User With Role',
        'email' => 'userrole@example.com',
        'password' => Hash::make('password'),
    ]);
    $newUser->assignRole('member');

    expect($newUser->hasRole('member'))->toBeTrue()
        ->and($newUser->roles->count())->toBe(1)
        ->and($newUser->roles->first()->name)->toBe('member');
});

it('validates required fields when creating user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    expect(fn() => User::create(['email' => 'test@example.com', 'password' => Hash::make('password')]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('validates unique email when creating user', function () {
    $superAdmin = User::factory()->create(['email' => 'superadmin@example.com']);
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    User::create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'password' => Hash::make('password'),
    ]);

    expect(fn() => User::create([
        'name' => 'Duplicate User',
        'email' => 'existing@example.com',
        'password' => Hash::make('password'),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
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
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $adminRole = Role::create(['name' => 'admin']);
    $newUser = User::create([
        'name' => 'Multi Role User',
        'email' => 'multirole@example.com',
        'password' => Hash::make('password'),
    ]);
    $newUser->assignRole(['member', 'admin']);

    expect($newUser->hasRole('member'))->toBeTrue()
        ->and($newUser->hasRole('admin'))->toBeTrue()
        ->and($newUser->roles->count())->toBe(2);
});

it('sets email_verified_at when provided during user creation', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $newUser = User::create([
        'name' => 'Verified User',
        'email' => 'verified@example.com',
        'password' => Hash::make('password'),
    ]);
    $verifiedAt = now();
    $newUser->email_verified_at = $verifiedAt;
    $newUser->save();
    $newUser->refresh();

    expect($newUser->email_verified_at)->not->toBeNull()
        ->and($newUser->hasVerifiedEmail())->toBeTrue();
});

it('creates user without email verification when not provided', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $newUser = User::create([
        'name' => 'Unverified User',
        'email' => 'unverified@example.com',
        'password' => Hash::make('password'),
    ]);

    expect($newUser->email_verified_at)->toBeNull()
        ->and($newUser->hasVerifiedEmail())->toBeFalse();
});

it('allows super admin to create user with google_id for OAuth', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
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
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
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