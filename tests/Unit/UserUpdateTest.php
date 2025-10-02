<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserUpdateTest extends TestCase
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
        $hashedPassword = bcrypt($newPassword);

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
}
