<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_viewers_cannot_manage_users_or_see_the_menu(): void
    {
        $target = User::factory()->create();
        $this->get('/users')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->get('/dashboard')->assertOk()->assertDontSee(route('users.index'), false)->assertDontSee('Example two level');
        foreach (['/users', '/users/create', '/users/'.$target->id, '/users/'.$target->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/users')->assertForbidden();
        $this->put('/users/'.$target->id, ['name' => 'Changed'])->assertForbidden();
        $this->delete('/users/'.$target->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $target->name]);
    }

    public function test_admin_can_create_view_update_and_delete_user(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/dashboard')->assertSee(route('users.index'), false);
        $this->get('/users')->assertOk();
        $this->get('/users/create')->assertOk();
        $this->post('/users', ['name' => 'New member', 'email' => 'member@example.test', 'role' => 'viewer', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertRedirect();
        $user = User::where('email', 'member@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->get('/users/'.$user->id)->assertOk()->assertSee('New member');
        $this->get('/users/'.$user->id.'/edit')->assertOk();
        $password = $user->password;
        $this->put('/users/'.$user->id, ['name' => 'Updated member', 'email' => $user->email, 'role' => 'admin'])->assertRedirect();
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated member', 'role' => 'admin']);
        $this->put('/users/'.$user->id, ['name' => 'Updated member', 'email' => $user->email, 'role' => 'viewer', 'password' => 'another-password', 'password_confirmation' => 'another-password'])->assertRedirect();
        $this->assertTrue(Hash::check('another-password', $user->fresh()->password));
        $this->delete('/users/'.$user->id)->assertRedirect('/users');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->get('/users/'.$user->id)->assertNotFound();
    }

    public function test_validation_and_own_administrator_protection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->post('/users', ['name' => '', 'email' => $admin->email, 'role' => 'owner', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['name', 'email', 'role', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->delete('/users/'.$admin->id)->assertForbidden();
        $this->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'viewer'])->assertSessionHasErrors('role');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }
}
