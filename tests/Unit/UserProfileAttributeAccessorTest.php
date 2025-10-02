<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileAttributeAccessorTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE ATTRIBUTE ACCESSOR TESTS
    // ============================================

    public function testCanGetUnreadNotificationsCount(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $count = $user->unread_notifications_count;

        // Assert
        $this->assertIsInt($count);
        $this->assertEquals(0, $count);
    }
}
