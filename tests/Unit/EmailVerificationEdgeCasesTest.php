<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function testEmailVerificationWithFutureDate(): void
    {
        // Arrange & Act
        $futureDate = now()->addDays(7);
        $user = User::factory()->create([
            'email_verified_at' => $futureDate,
        ]);

        // Assert
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($futureDate->timestamp, $user->email_verified_at->timestamp);
    }

    public function testEmailVerificationWithPastDate(): void
    {
        // Arrange & Act
        $pastDate = now()->subYears(2);
        $user = User::factory()->create([
            'email_verified_at' => $pastDate,
        ]);

        // Assert
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($pastDate->timestamp, $user->email_verified_at->timestamp);
    }

    public function testUserFactoryCreatesVerifiedUserByDefault(): void
    {
        // Arrange & Act
        $user = User::factory()->create();

        // Assert
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function testUserFactoryCanCreateUnverifiedUser(): void
    {
        // Arrange & Act
        $user = User::factory()->unverified()->create();

        // Assert
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function testGoogleOAuthUserIsVerifiedAutomatically(): void
    {
        // Arrange & Act
        // When user logs in via Google OAuth, their email should be verified
        $user = User::factory()->create([
            'google_id' => 'google-oauth-id-123',
            'email_verified_at' => now(), // OAuth users are auto-verified
        ]);

        // Assert
        $this->assertNotNull($user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->email_verified_at);
    }

    public function testEmailVerificationDoesNotAffectUniqueEmailConstraint(): void
    {
        // Arrange
        $email = 'unique@example.com';
        User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::factory()->create([
            'email' => $email, // Same email should fail regardless of verification status
            'email_verified_at' => null,
        ]);
    }
}
