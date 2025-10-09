<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailVerificationStateTest extends TestCase
{
    use RefreshDatabase;

    public function testNewUserIsUnverifiedByDefault(): void
    {
        // Arrange & Act
        $user = User::factory()->unverified()->create();

        // Assert
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function testUserCanBeCreatedWithVerifiedEmail(): void
    {
        // Arrange & Act
        $verifiedAt = now();
        $user = User::factory()->create([
            'email_verified_at' => $verifiedAt,
        ]);

        // Assert
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($verifiedAt->timestamp, $user->email_verified_at->timestamp);
    }

    public function testCanCheckIfUserEmailIsVerified(): void
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
        // Arrange & Act
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Assert
        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    public function testEmailVerifiedAtAcceptsStringAndCastsToDateTime(): void
    {
        // Arrange & Act
        $dateString = '2024-01-15 10:30:00';
        $user = User::factory()->create([
            'email_verified_at' => $dateString,
        ]);

        // Assert
        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
        $this->assertEquals('2024-01-15 10:30:00', $user->email_verified_at->format('Y-m-d H:i:s'));
    }

    public function testEmailVerifiedAtCanBeNull(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Assert
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function testEmailVerificationPersistsAcrossRefresh(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $originalVerifiedAt = $user->email_verified_at;

        // Act
        $user->refresh();

        // Assert
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($originalVerifiedAt->timestamp, $user->email_verified_at->timestamp);
    }
}
