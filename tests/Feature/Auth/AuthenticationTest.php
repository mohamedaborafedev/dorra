<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_remember_me_creates_a_token_and_restores_login_without_the_session(): void
    {
        $user = User::factory()->create(['remember_token' => null]);
        $html = $this->get('/login')->getContent();
        preg_match('/<input type="checkbox"[^>]*name="remember"[^>]*value="([^"]*)"/', $html, $matches);
        $this->assertSame('1', $matches[1] ?? null);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => $matches[1],
        ]);
        $cookieName = \Illuminate\Support\Facades\Auth::guard('web')->getRecallerName();
        $response->assertCookie($cookieName);
        $this->assertNotEmpty($user->fresh()->remember_token);
        $cookie = $response->getCookie($cookieName);

        session()->flush();
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->withCookie($cookieName, $cookie->getValue())->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(\Illuminate\Support\Facades\Auth::viaRemember());
    }

    public function test_login_without_remember_me_does_not_create_a_remember_token_or_cookie(): void
    {
        $user = User::factory()->create(['remember_token' => null]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ]);
        $response->assertCookieMissing(\Illuminate\Support\Facades\Auth::guard('web')->getRecallerName());
        $this->assertNull($user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
