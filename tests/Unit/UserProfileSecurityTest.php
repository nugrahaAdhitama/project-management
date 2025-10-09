<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE SECURITY TESTS
    // ============================================

    public function testPasswordHashIsNotReversible(): void
    {
        // Arrange
        $plainPassword = 'my-secret-password';
        $user = User::factory()->create([
            'password' => bcrypt($plainPassword),
        ]);

        // Act & Assert
        // Hash should be one-way - cannot get plain password back
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertStringStartsWith('$2y$', $user->password); // bcrypt hash format
    }

    public function testDifferentPasswordsProduceDifferentHashes(): void
    {
        // Arrange
        $password1 = 'password1';
        $password2 = 'password2';

        // Act
        $hash1 = bcrypt($password1);
        $hash2 = bcrypt($password2);

        // Assert
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testSamePasswordProducesDifferentHashesEachTime(): void
    {
        // Arrange
        $password = 'same-password';

        // Act
        $hash1 = bcrypt($password);
        $hash2 = bcrypt($password);

        // Assert: Even same password produces different hashes (due to salt)
        $this->assertNotEquals($hash1, $hash2);

        // But both hashes should verify correctly
        $this->assertTrue(password_verify($password, $hash1));
        $this->assertTrue(password_verify($password, $hash2));
    }
}
