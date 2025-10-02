<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordVerificationTest extends TestCase
{
    use RefreshDatabase;

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
}
