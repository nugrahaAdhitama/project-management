<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PASSWORD MANAGEMENT TESTS
    // ============================================

    public function testCanUpdateUserPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $oldPasswordHash = $user->password;

        // Act
        $newPassword = 'new-secure-password';
        $user->update(['password' => bcrypt($newPassword)]);

        // Assert
        $user->refresh();
        $this->assertNotEquals($oldPasswordHash, $user->password);
        $this->assertTrue(password_verify($newPassword, $user->password));
    }

    public function testPasswordIsHashedWhenSet(): void
    {
        // Arrange & Act
        $plainPassword = 'my-plain-password';
        $user = User::factory()->create([
            'password' => bcrypt($plainPassword),
        ]);

        // Assert: Password should be hashed, not plain text
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(password_verify($plainPassword, $user->password));
    }

    public function testCanVerifyPasswordIsCorrect(): void
    {
        // Arrange
        $password = 'secret-password';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        // Act & Assert
        $this->assertTrue(password_verify($password, $user->password));
        $this->assertFalse(password_verify('wrong-password', $user->password));
    }

    public function testOldPasswordBecomesInvalidAfterPasswordChange(): void
    {
        // Arrange
        $oldPassword = 'old-password';
        $newPassword = 'new-password';

        $user = User::factory()->create([
            'password' => bcrypt($oldPassword),
        ]);

        // Verify old password works
        $this->assertTrue(password_verify($oldPassword, $user->password));

        // Act: Change password
        $user->update(['password' => bcrypt($newPassword)]);
        $user->refresh();

        // Assert: Old password no longer works
        $this->assertFalse(password_verify($oldPassword, $user->password));
        $this->assertTrue(password_verify($newPassword, $user->password));
    }

    public function testPasswordIsNotReturnedInJsonResponse(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => bcrypt('secret'),
        ]);

        // Act
        $json = $user->toJson();
        $decoded = json_decode($json, true);

        // Assert
        $this->assertArrayNotHasKey('password', $decoded);
    }
}
