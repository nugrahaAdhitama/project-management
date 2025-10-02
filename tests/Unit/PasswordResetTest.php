<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function testCanCreatePasswordResetToken(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        // Act
        $token = Password::createToken($user);

        // Assert
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertGreaterThan(0, strlen($token));
    }

    public function testPasswordResetTokenExistsInDatabase(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        // Act
        Password::createToken($user);

        // Assert
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function testCanValidatePasswordResetToken(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);
        Password::createToken($user);

        // Act & Assert
        // Token should exist and be valid for the user
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function testPasswordResetTokenIsUnique(): void
    {
        // Arrange
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // Act
        $token1 = Password::createToken($user1);
        $token2 = Password::createToken($user2);

        // Assert
        $this->assertNotEquals($token1, $token2);
    }

    public function testCreatingNewTokenInvalidatesOldToken(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        // Act
        $oldToken = Password::createToken($user);
        $newToken = Password::createToken($user);

        // Assert
        $this->assertNotEquals($oldToken, $newToken);

        // Only one token should exist for the user
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function testCanResetPasswordWithValidToken(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);
        $newPassword = 'new-secure-password';

        // Act
        $status = Password::reset(
            [
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'token' => $token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        // Assert
        $this->assertEquals(Password::PASSWORD_RESET, $status);

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function testCannotResetPasswordWithInvalidToken(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('old-password'),
        ]);

        Password::createToken($user);
        $invalidToken = 'invalid-token-123';
        $newPassword = 'new-password';

        // Act
        $status = Password::reset(
            [
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'token' => $invalidToken,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        // Assert
        $this->assertEquals(Password::INVALID_TOKEN, $status);

        // Password should remain unchanged
        $user->refresh();
        $this->assertTrue(Hash::check('old-password', $user->password));
    }

    public function testCannotResetPasswordWithInvalidEmail(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $token = Password::createToken($user);
        $newPassword = 'new-password';

        // Act
        $status = Password::reset(
            [
                'email' => 'wrong@example.com',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'token' => $token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        // Assert
        $this->assertEquals(Password::INVALID_USER, $status);
    }

    public function testPasswordResetTokenIsDeletedAfterSuccessfulReset(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);
        $newPassword = 'new-password';

        // Verify token exists
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Act
        Password::reset(
            [
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'token' => $token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        // Assert: Token should be deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function testCanResetPasswordMultipleTimesSequentially(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('initial-password'),
        ]);

        // Act & Assert: Multiple sequential resets
        $passwords = ['reset1', 'reset2', 'reset3'];

        foreach ($passwords as $newPassword) {
            $token = Password::createToken($user);

            $status = Password::reset(
                [
                    'email' => $user->email,
                    'password' => $newPassword,
                    'password_confirmation' => $newPassword,
                    'token' => $token,
                ],
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();
                }
            );

            $this->assertEquals(Password::PASSWORD_RESET, $status);

            $user->refresh();
            $this->assertTrue(Hash::check($newPassword, $user->password));
        }
    }
}
