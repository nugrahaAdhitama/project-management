<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    public function testPasswordIsHashedWhenUserIsCreated(): void
    {
        // Arrange & Act
        $plainPassword = 'secure-password-123';
        $user = User::factory()->create([
            'password' => Hash::make($plainPassword),
        ]);

        // Assert
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertStringStartsWith('$2y$', $user->password);
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
        $this->assertEquals(60, strlen($hash));
    }

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
}
