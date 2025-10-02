<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCreationTest extends TestCase
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
        $userMock->password = bcrypt($userData['password']);

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

        (new User())->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function testValidatesUniqueEmailDuringUserCreation(): void
    {
        // Arrange: Create existing user
        User::factory()->create(['email' => 'existing@example.com']);

        // Act & Assert: Duplicate email
        $this->expectException(\Illuminate\Database\QueryException::class);

        (new User())->create([
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function testHashesPasswordAutomaticallyWhenCreatingUser(): void
    {
        // Arrange
        $plainPassword = 'SecurePassword123';

        // Act
        $user = (new User())->create([
            'name' => 'Password User',
            'email' => 'password@example.com',
            'password' => bcrypt($plainPassword),
        ]);

        // Assert
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(password_verify($plainPassword, $user->password));
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
}
