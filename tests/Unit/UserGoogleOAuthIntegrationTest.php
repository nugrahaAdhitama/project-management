<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGoogleOAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // GOOGLE OAUTH INTEGRATION TESTS
    // ============================================

    public function testCanSetGoogleIdOnUserProfile(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $googleId = '1234567890';
        $user->update(['google_id' => $googleId]);

        // Assert
        $user->refresh();
        $this->assertEquals($googleId, $user->google_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => $googleId,
        ]);
    }

    public function testCanCreateUserWithGoogleId(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'name' => 'Google User',
            'email' => 'google@example.com',
            'google_id' => 'google-123456',
        ]);

        // Assert
        $this->assertEquals('google-123456', $user->google_id);
    }

    public function testCanFindUserByGoogleId(): void
    {
        // Arrange
        $googleId = 'unique-google-id-123';
        $user = User::factory()->create([
            'google_id' => $googleId,
        ]);

        // Act
        $foundUser = (new User())->where('google_id', $googleId)->first();

        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals($googleId, $foundUser->google_id);
    }

    public function testGoogleIdCanBeNull(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'google_id' => null,
        ]);

        // Assert
        $this->assertNull($user->google_id);
    }
}
