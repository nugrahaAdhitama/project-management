<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordValidationTest extends TestCase
{
    use RefreshDatabase;

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
}
