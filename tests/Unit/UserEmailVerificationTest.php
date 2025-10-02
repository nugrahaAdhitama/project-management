<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // EMAIL VERIFICATION TESTS
    // ============================================

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
    }

    public function testCanCheckIfEmailIsVerified(): void
    {
        // Arrange
        $verifiedUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $unverifiedUser = User::factory()->unverified()->create();

        // Act & Assert
        $this->assertTrue($verifiedUser->hasVerifiedEmail());
        $this->assertFalse($unverifiedUser->hasVerifiedEmail());
    }

    public function testEmailVerifiedAtIsCastToDateTime(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    public function testCanCreateUnverifiedUser(): void
    {
        // Arrange & Act
        $user = User::factory()->unverified()->create();

        // Assert
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }
}
