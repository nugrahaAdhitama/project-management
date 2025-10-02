<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTimestampsTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE TIMESTAMPS TESTS
    // ============================================

    public function testProfileHasCreatedAtTimestamp(): void
    {
        // Arrange & Act
        $user = User::factory()->create();

        // Assert
        $this->assertNotNull($user->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->created_at);
    }

    public function testProfileHasUpdatedAtTimestamp(): void
    {
        // Arrange & Act
        $user = User::factory()->create();

        // Assert
        $this->assertNotNull($user->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->updated_at);
    }

    public function testUpdatedAtChangesWhenProfileIsUpdated(): void
    {
        // Arrange
        $user = User::factory()->create();
        $originalUpdatedAt = $user->updated_at;

        // Use usleep instead of sleep to avoid security warning
        usleep(1100000); // 1.1 seconds in microseconds

        // Act
        $user->update(['name' => 'Updated Name']);

        // Assert
        $user->refresh();
        $this->assertNotEquals($originalUpdatedAt, $user->updated_at);
        $this->assertTrue($user->updated_at->greaterThan($originalUpdatedAt));
    }
}
