<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileRetrievalTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE VIEWING/RETRIEVAL TESTS
    // ============================================

    public function testCanRetrieveUserProfile(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Act
        $profile = (new User())->find($user->id);

        // Assert
        $this->assertNotNull($profile);
        $this->assertEquals('John Doe', $profile->name);
        $this->assertEquals('john@example.com', $profile->email);
    }

    public function testUserProfileContainsCorrectAttributes(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert
        $this->assertNotNull($user->id);
        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function testUserProfileHidesPasswordFromSerialization(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);

        // Act
        $userArray = $user->toArray();

        // Assert: Password should not be in the array
        $this->assertArrayNotHasKey('password', $userArray);
    }

    public function testUserProfileHidesRememberTokenFromSerialization(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $userArray = $user->toArray();

        // Assert: Remember token should not be in the array
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function testCanAccessUserProfileWithRelationships(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act: Access relationships
        $projects = $user->projects;
        $assignedTickets = $user->assignedTickets;
        $createdTickets = $user->createdTickets;
        $notifications = $user->notifications;

        // Assert: Relationships are accessible
        $this->assertNotNull($projects);
        $this->assertNotNull($assignedTickets);
        $this->assertNotNull($createdTickets);
        $this->assertNotNull($notifications);
    }
}
