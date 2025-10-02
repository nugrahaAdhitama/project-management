<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileEmailUpdateTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE UPDATE TESTS - EMAIL
    // ============================================

    public function testCanUpdateUserProfileEmail(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        // Act
        $user->update(['email' => 'new@example.com']);

        // Assert
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
        ]);
    }

    public function testCannotUpdateEmailToDuplicateEmail(): void
    {
        // Arrange
        User::factory()->create(['email' => 'existing@example.com']);
        $user = User::factory()->create(['email' => 'original@example.com']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        $user->update(['email' => 'existing@example.com']);
        $user->saveOrFail();
    }

    public function testCanManuallyResetEmailVerificationWhenUpdatingEmail(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->assertNotNull($user->email_verified_at);

        // Act: Update email and manually reset verification
        $user->email = 'new@example.com';
        $user->email_verified_at = null;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function testEmailVerificationRemainsAfterNameUpdate(): void
    {
        // Arrange
        $verifiedAt = now();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email_verified_at' => $verifiedAt,
        ]);

        // Act: Update only name (email unchanged)
        $user->update(['name' => 'Updated Name']);

        // Assert: Email verification should remain
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function testCanHandleVeryLongEmail(): void
    {
        // Arrange
        // Email with very long local part
        $longEmail = str_repeat('a', 50) . '@example.com';
        $user = User::factory()->create();

        // Act
        $user->update(['email' => $longEmail]);

        // Assert
        $user->refresh();
        $this->assertEquals($longEmail, $user->email);
    }
}
