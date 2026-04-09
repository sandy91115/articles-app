<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('uploads/profile-photos'));

        parent::tearDown();
    }

    public function test_reader_can_register_verify_and_login(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Reader One',
            'email' => 'reader.one@example.com',
            'phone' => '9876543210',
            'user' => 'reader.one',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'reader',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('user.email', 'reader.one@example.com')
            ->assertJsonPath('user.username', 'reader.one');

        $verificationCode = $registerResponse->json('debug_code');

        $this->assertNotNull($verificationCode);

        $verifyResponse = $this->postJson('/api/auth/verify-otp', [
            'email' => 'reader.one@example.com',
            'code' => $verificationCode,
            'device_name' => 'phpunit',
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('user.email', 'reader.one@example.com')
            ->assertJsonStructure(['token', 'token_type']);

        $this->assertNotNull(
            User::query()->where('email', 'reader.one@example.com')->first()?->email_verified_at,
        );

        $this->postJson('/api/auth/login', [
            'email' => 'reader.one@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit-login',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_author_signup_remains_compatible_without_reader_specific_fields(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Author One',
            'email' => 'author.one@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'author',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('user.email', 'author.one@example.com')
            ->assertJsonPath('user.username', null)
            ->assertJsonPath('user.role', 'author');
    }

    public function test_authenticated_reader_can_update_profile_photo_and_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'profile.reader@example.com',
            'password' => 'password123',
            'role' => 'reader',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('phpunit')->plainTextToken;

        $photoResponse = $this
            ->withToken($token)
            ->post('/api/auth/profile-photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 320, 320),
            ]);

        $photoResponse
            ->assertOk()
            ->assertJsonPath('message', 'Profile photo updated successfully.');

        $this->assertStringContainsString(
            '/uploads/profile-photos/',
            (string) $photoResponse->json('user.profile_photo_url'),
        );

        $changePasswordResponse = $this
            ->withToken($token)
            ->putJson('/api/auth/password', [
                'current_password' => 'password123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $changePasswordResponse
            ->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $this->postJson('/api/auth/login', [
            'email' => 'profile.reader@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit-old-password',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'profile.reader@example.com',
            'password' => 'newpassword123',
            'device_name' => 'phpunit-new-password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }
}
