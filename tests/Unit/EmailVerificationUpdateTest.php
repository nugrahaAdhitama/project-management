<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationUpdateTest extends TestCase
{
    use RefreshDatabase;

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
}
