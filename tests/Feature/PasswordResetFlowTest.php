<?php

namespace Tests\Feature;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_password_reset_flow()
    {
        Mail::fake();

        // 1. Create a user
        $user = User::factory()->create([
            'Email' => 'test@example.com',
            'PasswordHash' => Hash::make('OldPassword123'),
        ]);

        // 2. Request password reset code
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        // Get the token from DB
        $tokenRecord = DB::table('password_reset_tokens')->where('email', 'test@example.com')->first();
        $this->assertNotNull($tokenRecord);

        // We can't easily get the plain token because it is hashed in DB. 
        // For testing purposes, we will update the token in DB to a known value's hash.
        // In a real scenario, the user gets it from email.
        $knownToken = '123456';
        DB::table('password_reset_tokens')->where('email', 'test@example.com')->update([
            'token' => Hash::make($knownToken)
        ]);

        // 3. Verify the code (Success)
        $verifyResponse = $this->postJson('/api/auth/verify-reset-code', [
            'email' => 'test@example.com',
            'token' => $knownToken,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'valid' => true,
            ]);

        // 4. Verify the code (Failure - Invalid Code)
        $invalidVerifyResponse = $this->postJson('/api/auth/verify-reset-code', [
            'email' => 'test@example.com',
            'token' => '000000',
        ]);

        $invalidVerifyResponse->assertStatus(422);

        // 5. Reset Password
        $resetResponse = $this->postJson('/api/auth/reset-password', [
            'email' => 'test@example.com',
            'token' => $knownToken,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $resetResponse->assertStatus(200);

        // Verify login with new password
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->PasswordHash));
    }
}
