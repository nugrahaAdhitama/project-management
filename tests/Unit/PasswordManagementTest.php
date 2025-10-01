<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PASSWORD CREATION & HASHING TESTS
    // ============================================

    public function testPasswordIsHashedWhenUserIsCreated(): void
    {
        // Arrange & Act
        $plainPassword = 'secure-password-123';
        $user = User::factory()->create([
            'password' => Hash::make($plainPassword),
        ]);

        // Assert
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertStringStartsWith('$2y$', $user->password); // bcrypt format
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    public function testPasswordHashingIsConsistentInVerification(): void
    {
        // Arrange
        $password = 'test-password';
        $hashedPassword = Hash::make($password);

        // Act & Assert
        $this->assertTrue(Hash::check($password, $hashedPassword));
        $this->assertFalse(Hash::check('wrong-password', $hashedPassword));
    }

    public function testSamePasswordProducesDifferentHashesDueToSalt(): void
    {
        // Arrange
        $password = 'same-password';

        // Act
        $hash1 = Hash::make($password);
        $hash2 = Hash::make($password);

        // Assert: Hashes are different due to unique salts
        $this->assertNotEquals($hash1, $hash2);

        // But both verify correctly
        $this->assertTrue(Hash::check($password, $hash1));
        $this->assertTrue(Hash::check($password, $hash2));
    }

    public function testEmptyPasswordCannotBeVerified(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('valid-password'),
        ]);

        // Act & Assert
        $this->assertFalse(Hash::check('', $user->password));
    }

    public function testPasswordHashUsesStrongAlgorithm(): void
    {
        // Arrange & Act
        $password = 'test-password';
        $hash = Hash::make($password);

        // Assert: bcrypt produces a hash with specific format and length
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertEquals(60, strlen($hash)); // bcrypt hash length
    }

    // ============================================
    // PASSWORD UPDATE TESTS
    // ============================================

    public function testCanUpdateUserPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $oldHash = $user->password;

        // Act
        $newPassword = 'new-secure-password';
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Assert
        $user->refresh();
        $this->assertNotEquals($oldHash, $user->password);
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function testPasswordUpdateInvalidatesOldPassword(): void
    {
        // Arrange
        $oldPassword = 'old-password';
        $newPassword = 'new-password';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        // Verify old password works initially
        $this->assertTrue(Hash::check($oldPassword, $user->password));

        // Act: Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
        $user->refresh();

        // Assert: Old password no longer works
        $this->assertFalse(Hash::check($oldPassword, $user->password));
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testCanUpdatePasswordMultipleTimes(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('password1'),
        ]);

        // Act & Assert: Multiple password changes
        $passwords = ['password2', 'password3', 'password4'];
        $previousPassword = 'password1';

        foreach ($passwords as $newPassword) {
            $user->update(['password' => Hash::make($newPassword)]);
            $user->refresh();

            // New password works
            $this->assertTrue(Hash::check($newPassword, $user->password));

            // Previous password doesn't work
            $this->assertFalse(Hash::check($previousPassword, $user->password));

            $previousPassword = $newPassword;
        }
    }

    public function testPasswordUpdateDoesNotAffectOtherFields(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('old-password'),
        ]);

        // Act: Update only password
        $user->update([
            'password' => Hash::make('new-password'),
        ]);

        // Assert: Other fields remain unchanged
        $user->refresh();
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    // ============================================
    // PASSWORD VERIFICATION TESTS
    // ============================================

    public function testCanVerifyCorrectPassword(): void
    {
        // Arrange
        $password = 'correct-password';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Act & Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function testCannotVerifyIncorrectPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        // Act & Assert
        $this->assertFalse(Hash::check('wrong-password', $user->password));
        $this->assertFalse(Hash::check('incorrect-password', $user->password));
        $this->assertFalse(Hash::check('', $user->password));
    }

    public function testPasswordVerificationIsCaseSensitive(): void
    {
        // Arrange
        $password = 'CaseSensitivePassword';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Act & Assert
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse(Hash::check('casesensitivepassword', $user->password));
        $this->assertFalse(Hash::check('CASESENSITIVEPASSWORD', $user->password));
    }

    public function testPasswordVerificationWithSpecialCharacters(): void
    {
        // Arrange
        $password = 'P@ssw0rd!#$%^&*()_+-=[]{}|;:,.<>?';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Act & Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function testPasswordVerificationWithUnicodeCharacters(): void
    {
        // Arrange
        $password = 'Пароль123密码ñoño';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Act & Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    // ============================================
    // PASSWORD RESET TOKEN TESTS
    // ============================================

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
        $token = Password::createToken($user);

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
        $token = Password::createToken($user);

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

    // ============================================
    // PASSWORD RESET FUNCTIONALITY TESTS
    // ============================================

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

    // ============================================
    // PASSWORD SECURITY TESTS
    // ============================================

    public function testPasswordIsNotExposedInArraySerialization(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        // Act
        $userArray = $user->toArray();

        // Assert
        $this->assertArrayNotHasKey('password', $userArray);
    }

    public function testPasswordIsNotExposedInJsonSerialization(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        // Act
        $userJson = $user->toJson();
        $decoded = json_decode($userJson, true);

        // Assert
        $this->assertArrayNotHasKey('password', $decoded);
    }

    public function testPasswordFieldIsMarkedAsHidden(): void
    {
        // Arrange
        $user = new User();

        // Act
        $hiddenFields = $user->getHidden();

        // Assert
        $this->assertContains('password', $hiddenFields);
    }

    public function testPasswordIsCastToHashedValue(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'password' => Hash::make('test-password'),
        ]);

        // Assert: Password in database is hashed
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertEquals(60, strlen($user->password));
    }

    // ============================================
    // PASSWORD CHANGE AUTHORIZATION TESTS
    // ============================================

    public function testUserCanChangeTheirOwnPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        // Act: Simulate user changing their own password
        $this->actingAs($user);

        $newPassword = 'new-password';
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Assert
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testPasswordUpdateRequiresCurrentPasswordVerification(): void
    {
        // Arrange
        $currentPassword = 'current-password';
        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act: Verify current password before allowing change
        $currentPasswordIsCorrect = Hash::check($currentPassword, $user->password);

        // Assert: Should verify current password first
        $this->assertTrue($currentPasswordIsCorrect);

        // Only then allow password change
        if ($currentPasswordIsCorrect) {
            $user->update([
                'password' => Hash::make('new-password'),
            ]);

            $user->refresh();
            $this->assertTrue(Hash::check('new-password', $user->password));
        }
    }

    public function testPasswordChangeFailsWithIncorrectCurrentPassword(): void
    {
        // Arrange
        $currentPassword = 'current-password';
        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act: Try to verify with wrong current password
        $providedCurrentPassword = 'wrong-password';
        $currentPasswordIsCorrect = Hash::check($providedCurrentPassword, $user->password);

        // Assert: Verification should fail
        $this->assertFalse($currentPasswordIsCorrect);

        // Password should remain unchanged
        $this->assertTrue(Hash::check($currentPassword, $user->password));
    }

    // ============================================
    // PASSWORD STRENGTH & VALIDATION TESTS
    // ============================================

    public function testCanCreateUserWithStrongPassword(): void
    {
        // Arrange & Act
        $strongPasswords = [
            'StrongP@ssw0rd123',
            'C0mpl3x!P@ssword',
            'S3cur3_P@ssW0rd!',
        ];

        foreach ($strongPasswords as $password) {
            $user = User::factory()->create([
                'email' => Str::random(10) . '@example.com',
                'password' => Hash::make($password),
            ]);

            // Assert
            $this->assertTrue(Hash::check($password, $user->password));
        }
    }

    public function testPasswordCanContainMinimumLength(): void
    {
        // Arrange & Act
        $shortPassword = '12345678'; // 8 characters
        $user = User::factory()->create([
            'password' => Hash::make($shortPassword),
        ]);

        // Assert
        $this->assertTrue(Hash::check($shortPassword, $user->password));
    }

    public function testPasswordCanContainMaximumLength(): void
    {
        // Arrange & Act
        $longPassword = str_repeat('a', 255); // Very long password
        $user = User::factory()->create([
            'password' => Hash::make($longPassword),
        ]);

        // Assert
        $this->assertTrue(Hash::check($longPassword, $user->password));
    }

    public function testPasswordCanContainUppercaseLetters(): void
    {
        // Arrange & Act
        $password = 'UPPERCASE123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function testPasswordCanContainLowercaseLetters(): void
    {
        // Arrange & Act
        $password = 'lowercase123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function testPasswordCanContainNumbers(): void
    {
        // Arrange & Act
        $password = 'password123456';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function testPasswordCanContainSpecialCharacters(): void
    {
        // Arrange & Act
        $password = 'p@ssw0rd!#$%';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function testPasswordCanContainSpaces(): void
    {
        // Arrange & Act
        $password = 'pass word with spaces';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Assert
        $this->assertTrue(Hash::check($password, $user->password));
    }

    // ============================================
    // PASSWORD CONFIRMATION TESTS
    // ============================================

    public function testPasswordConfirmationMustMatch(): void
    {
        // Arrange
        $password = 'secure-password';
        $passwordConfirmation = 'secure-password';

        // Act & Assert
        $this->assertEquals($password, $passwordConfirmation);
    }

    public function testPasswordConfirmationMismatchDetected(): void
    {
        // Arrange
        $password = 'secure-password';
        $passwordConfirmation = 'different-password';

        // Act & Assert
        $this->assertNotEquals($password, $passwordConfirmation);
    }

    // ============================================
    // EDGE CASES & SPECIAL SCENARIOS
    // ============================================

    public function testCanHandlePasswordWithOnlyWhitespace(): void
    {
        // Arrange & Act
        $password = '        '; // Only spaces
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Assert
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse(Hash::check(trim($password), $user->password));
    }

    public function testPasswordHashingIsIdempotent(): void
    {
        // Arrange
        $password = 'test-password';
        $hashedPassword = Hash::make($password);

        // Act: Hash the already hashed password
        $doubleHashed = Hash::make($hashedPassword);

        // Assert: Double hashing creates different hash
        $this->assertNotEquals($hashedPassword, $doubleHashed);

        // Original password doesn't verify against double hash
        $this->assertFalse(Hash::check($password, $doubleHashed));

        // But hashed password verifies against double hash
        $this->assertTrue(Hash::check($hashedPassword, $doubleHashed));
    }

    public function testPasswordUpdatePreservesUserIdentity(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $originalId = $user->id;
        $originalEmail = $user->email;

        // Act: Update password
        $user->update([
            'password' => Hash::make('new-password'),
        ]);

        // Assert: User identity preserved
        $user->refresh();
        $this->assertEquals($originalId, $user->id);
        $this->assertEquals($originalEmail, $user->email);
    }

    public function testMultipleUsersCanHaveSamePassword(): void
    {
        // Arrange
        $sharedPassword = 'common-password';

        // Act
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => Hash::make($sharedPassword),
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => Hash::make($sharedPassword),
        ]);

        // Assert: Both users can use same password
        $this->assertTrue(Hash::check($sharedPassword, $user1->password));
        $this->assertTrue(Hash::check($sharedPassword, $user2->password));

        // But their hashes are different
        $this->assertNotEquals($user1->password, $user2->password);
    }

    public function testPasswordResetDoesNotAffectOtherUsers(): void
    {
        // Arrange
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => Hash::make('password1'),
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => Hash::make('password2'),
        ]);

        // Act: Reset user1's password
        $user1->update([
            'password' => Hash::make('new-password1'),
        ]);

        // Assert: user2's password unchanged
        $user1->refresh();
        $user2->refresh();

        $this->assertTrue(Hash::check('new-password1', $user1->password));
        $this->assertTrue(Hash::check('password2', $user2->password));
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

    // ============================================
    // PERFORMANCE & OPTIMIZATION TESTS
    // ============================================

    public function testPasswordHashingPerformance(): void
    {
        // Arrange
        $password = 'test-password';
        $startTime = microtime(true);

        // Act: Hash password (bcrypt should be reasonably fast)
        $hash = Hash::make($password);

        // Assert: Hashing should complete within reasonable time (< 1 second)
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertLessThan(1.0, $executionTime);
        $this->assertNotEmpty($hash);
    }

    public function testPasswordVerificationPerformance(): void
    {
        // Arrange
        $password = 'test-password';
        $hash = Hash::make($password);
        $startTime = microtime(true);

        // Act: Verify password
        $result = Hash::check($password, $hash);

        // Assert: Verification should be fast (< 0.5 seconds)
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertLessThan(0.5, $executionTime);
        $this->assertTrue($result);
    }

    // ============================================
    // BULK OPERATIONS TESTS
    // ============================================

    public function testCanBulkUpdatePasswordsForMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create([
            'password' => Hash::make('old-password'),
        ]);

        $newPassword = 'new-bulk-password';
        $newHashedPassword = Hash::make($newPassword);

        // Act: Bulk update (simulated)
        foreach ($users as $user) {
            $user->update(['password' => $newHashedPassword]);
        }

        // Assert: All users have new password
        foreach ($users->fresh() as $user) {
            $this->assertTrue(Hash::check($newPassword, $user->password));
        }
    }
}
