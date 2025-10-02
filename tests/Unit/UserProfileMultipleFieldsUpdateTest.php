<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileMultipleFieldsUpdateTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE UPDATE - MULTIPLE FIELDS
    // ============================================

    public function testCanUpdateMultipleProfileFieldsAtOnce(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        // Act
        $user->update([
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        // Assert
        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
    }

    public function testCanUpdateProfileAndPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'password' => bcrypt('old-password'),
        ]);

        // Act
        $newPassword = 'new-password';
        $user->update([
            'name' => 'Jane Doe',
            'password' => bcrypt($newPassword),
        ]);

        // Assert
        $user->refresh();
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertTrue(password_verify($newPassword, $user->password));
    }

    public function testProfileUpdatePreservesOtherFields(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'google_id' => 'google-123',
        ]);

        // Act: Update only name
        $user->update(['name' => 'Updated Name']);

        // Assert: Other fields remain unchanged
        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('original@example.com', $user->email);
        $this->assertEquals('google-123', $user->google_id);
    }
}
