<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testCanQueryVerifiedUsers(): void
    {
        // Arrange
        User::factory()->count(3)->create(['email_verified_at' => now()]);
        User::factory()->unverified()->count(2)->create();

        // Act
        $verifiedUsers = User::whereNotNull('email_verified_at')->get();
        $unverifiedUsers = User::whereNull('email_verified_at')->get();

        // Assert
        $this->assertCount(3, $verifiedUsers);
        $this->assertCount(2, $unverifiedUsers);
    }

    public function testCanFilterUsersByEmailVerificationStatus(): void
    {
        // Arrange
        $verifiedUser = User::factory()->create(['email_verified_at' => now()]);
        $unverifiedUser = User::factory()->unverified()->create();

        // Act
        $foundVerified = User::whereNotNull('email_verified_at')->first();
        $foundUnverified = User::whereNull('email_verified_at')->first();

        // Assert
        $this->assertEquals($verifiedUser->id, $foundVerified->id);
        $this->assertEquals($unverifiedUser->id, $foundUnverified->id);
    }

    public function testCanCompareVerificationTimestamps(): void
    {
        // Arrange
        $olderUser = User::factory()->create([
            'email_verified_at' => now()->subDays(10),
        ]);
        $newerUser = User::factory()->create([
            'email_verified_at' => now()->subDays(5),
        ]);

        // Act & Assert
        $this->assertTrue($newerUser->email_verified_at->greaterThan($olderUser->email_verified_at));
        $this->assertTrue($olderUser->email_verified_at->lessThan($newerUser->email_verified_at));
    }

    public function testCanGetRecentlyVerifiedUsers(): void
    {
        // Arrange
        $recentlyVerified = User::factory()->create([
            'email_verified_at' => now()->subHours(2),
        ]);
        User::factory()->create([
            'email_verified_at' => now()->subMonths(6),
        ]);

        // Act
        $recent = User::where('email_verified_at', '>=', now()->subDays(1))->get();

        // Assert
        $this->assertCount(1, $recent);
        $this->assertEquals($recentlyVerified->id, $recent->first()->id);
    }
}
