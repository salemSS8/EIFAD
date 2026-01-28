<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use App\Domain\User\Models\User;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_provider_returns_url()
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('redirect')->andReturn($provider);
        $provider->shouldReceive('getTargetUrl')->andReturn('http://google.com/auth');

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/login/google');

        $response->assertStatus(200)
            ->assertJson(['url' => 'http://google.com/auth']);
    }

    public function test_social_callback_creates_user()
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('123456');
        $abstractUser->shouldReceive('getEmail')->andReturn('social@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Social User');
        $abstractUser->shouldReceive('getAvatar')->andReturn('avatar.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/login/google/callback');

        $response->assertStatus(200);
        $this->assertDatabaseHas('user', [
            'Email' => 'social@example.com',
            'ProviderID' => '123456',
            'AuthProvider' => 'google'
        ]);
    }
}
