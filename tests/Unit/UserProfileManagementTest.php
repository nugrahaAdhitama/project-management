<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class UserProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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
        $profile = User::find($user->id);

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
            'password' => Hash::make('secret-password'),
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

    // ============================================
    // PROFILE UPDATE TESTS - NAME
    // ============================================

    public function testCanUpdateUserProfileName(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        // Act
        $user->update(['name' => 'New Name']);

        // Assert
        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function testCanUpdateUserNameToEmptyString(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
        ]);

        // Act
        $user->update(['name' => '']);

        // Assert
        $user->refresh();
        $this->assertEquals('', $user->name);
    }

    public function testCanUpdateUserNameWithSpecialCharacters(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $specialName = "O'Brien-Smith, Jr.";
        $user->update(['name' => $specialName]);

        // Assert
        $user->refresh();
        $this->assertEquals($specialName, $user->name);
    }

    public function testCanUpdateUserNameWithUnicodeCharacters(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $unicodeName = "张三 李四 Müller Ñoño";
        $user->update(['name' => $unicodeName]);

        // Assert
        $user->refresh();
        $this->assertEquals($unicodeName, $user->name);
    }

    // ============================================
    // PROFILE UPDATE TESTS - EMAIL
    // ============================================

    public function testCanUpdateUserProfileEmail(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        // Act
        $user->update(['email' => 'new@example.com']);

        // Assert
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
        ]);
    }

    public function testCannotUpdateEmailToDuplicateEmail(): void
    {
        // Arrange
        User::factory()->create(['email' => 'existing@example.com']);
        $user = User::factory()->create(['email' => 'original@example.com']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        $user->update(['email' => 'existing@example.com']);
        $user->saveOrFail();
    }

    public function testCanManuallyResetEmailVerificationWhenUpdatingEmail(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->assertNotNull($user->email_verified_at);

        // Act: Update email and manually reset verification
        $user->email = 'new@example.com';
        $user->email_verified_at = null;
        $user->save();

        // Assert
        $user->refresh();
        $this->assertEquals('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function testEmailVerificationRemainsAfterNameUpdate(): void
    {
        // Arrange
        $verifiedAt = now();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email_verified_at' => $verifiedAt,
        ]);

        // Act: Update only name (email unchanged)
        $user->update(['name' => 'Updated Name']);

        // Assert: Email verification should remain
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    // ============================================
    // PASSWORD MANAGEMENT TESTS
    // ============================================

    public function testCanUpdateUserPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $oldPasswordHash = $user->password;

        // Act
        $newPassword = 'new-secure-password';
        $user->update(['password' => Hash::make($newPassword)]);

        // Assert
        $user->refresh();
        $this->assertNotEquals($oldPasswordHash, $user->password);
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testPasswordIsHashedWhenSet(): void
    {
        // Arrange & Act
        $plainPassword = 'my-plain-password';
        $user = User::factory()->create([
            'password' => Hash::make($plainPassword),
        ]);

        // Assert: Password should be hashed, not plain text
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    public function testCanVerifyPasswordIsCorrect(): void
    {
        // Arrange
        $password = 'secret-password';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Act & Assert
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse(Hash::check('wrong-password', $user->password));
    }

    public function testOldPasswordBecomesInvalidAfterPasswordChange(): void
    {
        // Arrange
        $oldPassword = 'old-password';
        $newPassword = 'new-password';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        // Verify old password works
        $this->assertTrue(Hash::check($oldPassword, $user->password));

        // Act: Change password
        $user->update(['password' => Hash::make($newPassword)]);
        $user->refresh();

        // Assert: Old password no longer works
        $this->assertFalse(Hash::check($oldPassword, $user->password));
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testPasswordIsNotReturnedInJsonResponse(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('secret'),
        ]);

        // Act
        $json = $user->toJson();
        $decoded = json_decode($json, true);

        // Assert
        $this->assertArrayNotHasKey('password', $decoded);
    }

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

    // ============================================
    // GOOGLE OAUTH INTEGRATION TESTS
    // ============================================

    public function testCanSetGoogleIdOnUserProfile(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $googleId = '1234567890';
        $user->update(['google_id' => $googleId]);

        // Assert
        $user->refresh();
        $this->assertEquals($googleId, $user->google_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => $googleId,
        ]);
    }

    public function testCanCreateUserWithGoogleId(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'name' => 'Google User',
            'email' => 'google@example.com',
            'google_id' => 'google-123456',
        ]);

        // Assert
        $this->assertEquals('google-123456', $user->google_id);
    }

    public function testCanFindUserByGoogleId(): void
    {
        // Arrange
        $googleId = 'unique-google-id-123';
        $user = User::factory()->create([
            'google_id' => $googleId,
        ]);

        // Act
        $foundUser = User::where('google_id', $googleId)->first();

        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals($googleId, $foundUser->google_id);
    }

    public function testGoogleIdCanBeNull(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'google_id' => null,
        ]);

        // Assert
        $this->assertNull($user->google_id);
    }

    // ============================================
    // PROFILE UPDATE - MULTIPLE FIELDS
    // ============================================

    public function testCanUpdateMultipleProfileFieldsAtOnce(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        // Act
        $user->update([
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        // Assert
        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
    }

    public function testCanUpdateProfileAndPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'password' => Hash::make('old-password'),
        ]);

        // Act
        $newPassword = 'new-password';
        $user->update([
            'name' => 'Jane Doe',
            'password' => Hash::make($newPassword),
        ]);

        // Assert
        $user->refresh();
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    // ============================================
    // PROFILE DATA VALIDATION TESTS
    // ============================================

    public function testUserProfileHasRequiredFields(): void
    {
        // Act & Assert: Missing name should throw exception
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function testEmailMustBeUniqueAcrossUsers(): void
    {
        // Arrange
        User::factory()->create(['email' => 'unique@example.com']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'name' => 'Another User',
            'email' => 'unique@example.com',
            'password' => Hash::make('password'),
        ]);
    }

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

        // Wait a bit to ensure timestamp difference
        sleep(1);

        // Act
        $user->update(['name' => 'Updated Name']);

        // Assert
        $user->refresh();
        $this->assertNotEquals($originalUpdatedAt, $user->updated_at);
        $this->assertTrue($user->updated_at->greaterThan($originalUpdatedAt));
    }

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

    // ============================================
    // PROFILE SECURITY TESTS
    // ============================================

    public function testPasswordHashIsNotReversible(): void
    {
        // Arrange
        $plainPassword = 'my-secret-password';
        $user = User::factory()->create([
            'password' => Hash::make($plainPassword),
        ]);

        // Act & Assert
        // Hash should be one-way - cannot get plain password back
        $this->assertNotEquals($plainPassword, $user->password);
        $this->assertStringStartsWith('$2y$', $user->password); // bcrypt hash format
    }

    public function testDifferentPasswordsProduceDifferentHashes(): void
    {
        // Arrange
        $password1 = 'password1';
        $password2 = 'password2';

        // Act
        $hash1 = Hash::make($password1);
        $hash2 = Hash::make($password2);

        // Assert
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testSamePasswordProducesDifferentHashesEachTime(): void
    {
        // Arrange
        $password = 'same-password';

        // Act
        $hash1 = Hash::make($password);
        $hash2 = Hash::make($password);

        // Assert: Even same password produces different hashes (due to salt)
        $this->assertNotEquals($hash1, $hash2);

        // But both hashes should verify correctly
        $this->assertTrue(Hash::check($password, $hash1));
        $this->assertTrue(Hash::check($password, $hash2));
    }

    // ============================================
    // PROFILE FILLABLE FIELDS TESTS
    // ============================================

    public function testOnlyFillableFieldsCanBeMassAssigned(): void
    {
        // Arrange & Act
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'google_id' => 'google-123',
            // email_verified_at is NOT fillable, so it should be ignored
        ]);

        // Assert: Fillable fields are set
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('google-123', $user->google_id);
    }

    public function testCanGetFillableFields(): void
    {
        // Arrange
        $user = new User();

        // Act
        $fillable = $user->getFillable();

        // Assert
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('google_id', $fillable);
    }

    // ============================================
    // PROFILE RETRIEVAL WITH MOCKING
    // ============================================

    public function testCanMockUserProfileRetrieval(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->id = 1;
        $userMock->name = 'Mocked User';
        $userMock->email = 'mocked@example.com';

        // Act & Assert
        $this->assertEquals('Mocked User', $userMock->name);
        $this->assertEquals('mocked@example.com', $userMock->email);
    }

    public function testCanMockProfileUpdateOperation(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('update')
            ->with(['name' => 'New Name'])
            ->once()
            ->andReturn(true);

        // Act
        $result = $userMock->update(['name' => 'New Name']);

        // Assert
        $this->assertTrue($result);
    }

    // ============================================
    // EDGE CASES
    // ============================================

    public function testCanHandleVeryLongName(): void
    {
        // Arrange
        $longName = str_repeat('A', 255);
        $user = User::factory()->create();

        // Act
        $user->update(['name' => $longName]);

        // Assert
        $user->refresh();
        $this->assertEquals($longName, $user->name);
    }

    public function testCanHandleVeryLongEmail(): void
    {
        // Arrange
        // Email with very long local part
        $longEmail = str_repeat('a', 50) . '@example.com';
        $user = User::factory()->create();

        // Act
        $user->update(['email' => $longEmail]);

        // Assert
        $user->refresh();
        $this->assertEquals($longEmail, $user->email);
    }

    public function testProfileUpdatePreservesOtherFields(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'google_id' => 'google-123',
        ]);

        // Act: Update only name
        $user->update(['name' => 'Updated Name']);

        // Assert: Other fields remain unchanged
        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('original@example.com', $user->email);
        $this->assertEquals('google-123', $user->google_id);
    }
}
