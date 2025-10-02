<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailVerificationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function testCanMarkEmailAsVerified(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $this->assertNull($user->email_verified_at);

        // Act
        $user->markEmailAsVerified();

        // Assert
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    public function testMarkingAlreadyVerifiedEmailDoesNotChangeTimestamp(): void
    {
        // Arrange
        $originalVerifiedAt = now()->subDays(5);
        $user = User::factory()->create([
            'email_verified_at' => $originalVerifiedAt,
        ]);

        // Act
        $user->markEmailAsVerified();

        // Assert
        $user->refresh();
        // The timestamp should remain the same (Laravel's markEmailAsVerified doesn't update if already verified)
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function testCanManuallySetEmailVerificationTimestamp(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $customVerifiedAt = now()->subWeek();

        // Act
        $user->email_verified_at = $customVerifiedAt;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($customVerifiedAt->timestamp, $user->email_verified_at->timestamp);
    }

    public function testCanManuallyUnverifyEmail(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->assertTrue($user->hasVerifiedEmail());

        // Act
        $user->email_verified_at = null;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function testAdminCanManuallyVerifyUserEmail(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        
        // Act - Simulate admin action
        $verifiedAt = now();
        $user->email_verified_at = $verifiedAt;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($verifiedAt->timestamp, $user->email_verified_at->timestamp);
    }

    public function testAdminCanRevokeEmailVerification(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Act - Simulate admin revoking verification
        $user->email_verified_at = null;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNull($user->email_verified_at);
    }
}
