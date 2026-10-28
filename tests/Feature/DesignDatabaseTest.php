<?php

namespace Tests\Feature;

use App\Enums\DesignStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Design;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DesignSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DesignDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_and_relationships(): void
    {
        $user = User::factory()->create();
        $this->assertSame(UserRole::Viewer, $user->fresh()->role);
        $category = Category::factory()->create();
        $id = DB::table('designs')->insertGetId(['category_id' => $category->id, 'title' => 'تصميم Modern']);
        $design = Design::findOrFail($id);
        $this->assertSame(DesignStatus::Draft, $design->status);
        $this->assertTrue($design->category->is($category));
        $this->assertTrue($category->designs->first()->is($design));
        $this->assertNull($design->thumbnail);
    }

    public function test_category_with_designs_cannot_be_deleted(): void
    {
        $design = Design::factory()->create();
        $this->expectException(QueryException::class);
        $design->category->delete();
    }

    public function test_design_cannot_reference_missing_category(): void
    {
        $this->expectException(QueryException::class);
        Design::factory()->create(['category_id' => 999999]);
    }

    public function test_category_slug_is_unique(): void
    {
        Category::factory()->create(['slug' => 'logos']);
        $this->expectException(QueryException::class);
        Category::factory()->create(['slug' => 'logos']);
    }

    public function test_sample_seeders_are_repeatable_and_preserve_existing_users(): void
    {
        $existing = User::factory()->create(['email' => 'admin@dorra.test']);
        $password = $existing->password;
        $this->seed([CategorySeeder::class, UserSeeder::class]);
        $this->seed([CategorySeeder::class, UserSeeder::class]);
        $this->assertDatabaseCount('categories', 10);
        $this->assertDatabaseCount('users', 2);
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertSame(UserRole::Viewer, $existing->fresh()->role);
    }

    public function test_design_seed_is_small_in_tests_and_can_resume(): void
    {
        $seeder = new DesignSeeder;
        $seeder->run(30);
        $seeder->run(37);
        $seeder->run(37);
        $this->assertDatabaseCount('designs', 37);
        $this->assertTrue(Design::where('title', 'like', 'Modern%')->exists());
        $this->assertTrue(Design::where('title', 'like', 'تصميم%')->exists());
        $this->assertSame(2, Design::distinct()->count('status'));
        $this->assertGreaterThan(1, Design::distinct()->count('created_at'));
    }

    public function test_user_seeder_creates_both_roles_with_hashed_passwords(): void
    {
        $this->seed(UserSeeder::class);
        $this->assertDatabaseHas('users', ['email' => 'admin@dorra.test', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'viewer@dorra.test', 'role' => 'viewer']);
        foreach (User::all() as $user) {
            $this->assertNotSame('unknown', password_get_info($user->password)['algoName']);
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $user->password));
        }
    }
}
