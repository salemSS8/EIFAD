<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_verification_code()
    {
        $user = User::factory()->create([
            'Email' => 'verify@example.com',
            'IsVerified' => false,
        ]);

        $response = $this->postJson('/api/auth/send-verification', [
            'email' => 'verify@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'debug_token']);

        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'verify@example.com',
        ]);
    }

    public function test_user_can_verify_account_with_valid_token()
    {
        $user = User::factory()->create([
            'Email' => 'verify@example.com',
            'IsVerified' => false,
        ]);

        $token = '123456';
        DB::table('email_verification_tokens')->insert([
            'email' => 'verify@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-account', [
            'email' => 'verify@example.com',
            'token' => $token,
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->IsVerified);
        $this->assertDatabaseMissing('email_verification_tokens', ['email' => 'verify@example.com']);
    }

    public function test_verify_account_fails_with_invalid_token()
    {
        $user = User::factory()->create(['Email' => 'fail@example.com', 'IsVerified' => false]);

        DB::table('email_verification_tokens')->insert([
            'email' => 'fail@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-account', [
            'email' => 'fail@example.com',
            'token' => '000000',
        ]);

        $response->assertStatus(422);

        $user->refresh();
        $this->assertFalse($user->IsVerified);
    }
}
