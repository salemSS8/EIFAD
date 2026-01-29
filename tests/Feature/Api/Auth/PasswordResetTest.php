<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Mail::fake();
    }

    public function test_user_can_request_password_reset_code()
    {
        $user = User::factory()->create([
            'Email' => 'reset@example.com',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonMissing(['debug_token']); // debug_token should be removed

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ResetPasswordCodeMail::class, function ($mail) {
            return $mail->hasTo('reset@example.com');
        });
    }

    public function test_user_can_reset_password_with_valid_token()
    {
        $user = User::factory()->create([
            'Email' => 'reset@example.com',
            'PasswordHash' => Hash::make('OldPassword123!'),
        ]);

        // Insert token manually
        $token = '123456';
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200);

        // Verify password changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->PasswordHash));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset@example.com']);
    }

    public function test_reset_password_fails_with_invalid_token()
    {
        $user = User::factory()->create(['Email' => 'fail@example.com']);

        DB::table('password_reset_tokens')->insert([
            'email' => 'fail@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'fail@example.com',
            'token' => '654321', // Wrong token
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'رمز إعادة التعيين غير صحيح']);
    }
}
