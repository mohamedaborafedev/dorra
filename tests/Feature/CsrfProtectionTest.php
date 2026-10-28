<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Laravel normally bypasses CSRF under PHPUnit. Explicitly enforce it here.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function test_all_mutating_web_endpoints_reject_missing_and_invalid_tokens(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $target = User::factory()->create();
        $design = Design::factory()->create();
        $routes = [
            ['POST', '/login'], ['POST', '/register'], ['POST', '/forgot-password'], ['POST', '/reset-password'],
            ['POST', '/confirm-password'], ['POST', '/verify-email'], ['POST', '/logout'],
            ['POST', '/users'], ['PUT', '/users/'.$target->id], ['DELETE', '/users/'.$target->id],
            ['PATCH', '/users/'.$target->id],
            ['POST', '/designs'], ['PUT', '/designs/'.$design->id], ['DELETE', '/designs/'.$design->id],
            ['PATCH', '/designs/'.$design->id],
            ['PUT', '/settings/profile'], ['DELETE', '/settings/profile'], ['PUT', '/settings/password'], ['PUT', '/settings/appearance'],
        ];
        foreach ($routes as [$method, $url]) {
            $this->withSession(['_token' => str_repeat('a', 40)])->call($method, $url)->assertStatus(419);
            $this->withSession(['_token' => str_repeat('a', 40)])->call($method, $url, ['_token' => 'wrong-token'])->assertStatus(419);
        }
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('designs', 1);
    }

    public function test_valid_token_allows_authorized_user_creation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $token = str_repeat('a', 40);
        $this->withSession(['_token' => $token])->post('/users', ['_token' => $token, 'name' => 'CSRF valid', 'email' => 'csrf@example.test', 'role' => 'viewer', 'password' => 'test-password', 'password_confirmation' => 'test-password'])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'csrf@example.test']);
    }
}
