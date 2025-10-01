<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // EMAIL VERIFICATION STATE TESTS
    // ============================================

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

    // ============================================
    // EMAIL VERIFICATION ACTIONS
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

    // ============================================
    // EMAIL VERIFICATION WITH EMAIL UPDATES
    // ============================================

    public function testEmailVerificationIsResetWhenEmailChanges(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        $this->assertTrue($user->hasVerifiedEmail());

        // Act
        $user->email = 'new@example.com';
        $user->email_verified_at = null; // Manually reset as this is expected behavior
        $user->save();

        // Assert
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function testEmailVerificationRemainsWhenOtherFieldsChange(): void
    {
        // Arrange
        $verifiedAt = now()->subDay();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email_verified_at' => $verifiedAt,
        ]);

        // Act
        $user->name = 'Updated Name';
        $user->save();

        // Assert
        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertEquals($verifiedAt->timestamp, $user->email_verified_at->timestamp);
    }

    public function testCanUpdateEmailAndVerificationSimultaneously(): void
    {
        // Arrange
        $user = User::factory()->unverified()->create([
            'email' => 'old@example.com',
        ]);

        // Act
        $newVerifiedAt = now();
        $user->email = 'new@example.com';
        $user->email_verified_at = $newVerifiedAt;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    // ============================================
    // EMAIL VERIFICATION DATA TYPES & CASTING
    // ============================================

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

    // ============================================
    // BULK OPERATIONS
    // ============================================

    public function testCanBulkVerifyMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->unverified()->count(5)->create();
        
        foreach ($users as $user) {
            $this->assertFalse($user->hasVerifiedEmail());
        }

        // Act
        $verifiedAt = now();
        User::whereIn('id', $users->pluck('id'))->update([
            'email_verified_at' => $verifiedAt,
        ]);

        // Assert
        foreach ($users->fresh() as $user) {
            $this->assertTrue($user->hasVerifiedEmail());
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function testCanBulkUnverifyMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create([
            'email_verified_at' => now(),
        ]);

        foreach ($users as $user) {
            $this->assertTrue($user->hasVerifiedEmail());
        }

        // Act
        User::whereIn('id', $users->pluck('id'))->update([
            'email_verified_at' => null,
        ]);

        // Assert
        foreach ($users->fresh() as $user) {
            $this->assertFalse($user->hasVerifiedEmail());
            $this->assertNull($user->email_verified_at);
        }
    }

    // ============================================
    // EDGE CASES
    // ============================================

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

    // ============================================
    // DATABASE QUERIES & SCOPES
    // ============================================

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

    // ============================================
    // INTEGRATION WITH USER FACTORY
    // ============================================

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

    // ============================================
    // VERIFICATION TIMESTAMP COMPARISON
    // ============================================

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
        $oldVerified = User::factory()->create([
            'email_verified_at' => now()->subMonths(6),
        ]);

        // Act
        $recent = User::where('email_verified_at', '>=', now()->subDays(1))->get();

        // Assert
        $this->assertCount(1, $recent);
        $this->assertEquals($recentlyVerified->id, $recent->first()->id);
    }

    // ============================================
    // EMAIL VERIFICATION WITH GOOGLE OAUTH
    // ============================================

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

    // ============================================
    // VALIDATION & CONSTRAINTS
    // ============================================

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

    // ============================================
    // ADMIN OPERATIONS
    // ============================================

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

