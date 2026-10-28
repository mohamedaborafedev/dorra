<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Design;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_list_filter_and_view_drafts(): void
    {
        $this->actingAs($this->admin());
        $draft = Design::factory()->create(['title' => 'Private draft', 'status' => 'draft']);
        Design::factory()->create(['title' => 'Public design', 'status' => 'published']);
        $this->get('/dashboard')->assertOk()->assertSee('Private draft')->assertSee('Public design');
        $this->get('/dashboard?status=draft')->assertOk()->assertSee('Private draft')->assertDontSee('Public design');
        $this->get("/designs/{$draft->id}")->assertOk();
    }

    public function test_shared_clothing_image_is_displayed_and_never_deleted_with_a_design(): void
    {
        $this->actingAs($this->admin());
        $design = Design::factory()->create(['title' => 'Modern Business Card 42', 'thumbnail' => 'clothing/clothing-01.jpg']);
        $this->assertSame('Modern Business Card', $design->table_title);
        $this->assertStringEndsWith('/clothing/clothing-01.jpg', $design->thumbnail_url);
        $path = public_path('clothing/clothing-01.jpg');
        $hash = hash_file('sha256', $path);
        $this->get('/dashboard')->assertOk()->assertSee('/clothing/clothing-01.jpg');
        $this->delete('/designs/'.$design->id)->assertRedirect();
        $this->assertSame($hash, hash_file('sha256', $path));
    }

    public function test_table_hides_sample_suffix_but_preserves_real_title_numbers(): void
    {
        $this->actingAs($this->admin());
        $sample = Design::factory()->create(['title' => 'Modern Business Card 55753', 'thumbnail' => 'placeholders/design.png']);
        $real = Design::factory()->create(['title' => 'Logo 2026']);
        $response = $this->get('/dashboard')->assertOk();
        $response->assertSee('dir="auto">Modern Business Card</a>', false);
        $response->assertSee('dir="auto">Logo 2026</a>', false);
        $this->assertSame('Modern Business Card 55753', $sample->fresh()->title);
        $this->assertSame('Logo 2026', $real->table_title);
    }

    public function test_guests_cannot_access_library_or_management(): void
    {
        $design = Design::factory()->create();
        foreach (['/dashboard', '/designs/create', "/designs/{$design->id}", "/designs/{$design->id}/edit"] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/designs')->assertRedirect('/login');
        $this->put("/designs/{$design->id}")->assertRedirect('/login');
        $this->delete("/designs/{$design->id}")->assertRedirect('/login');
    }

    public function test_viewer_only_sees_published_and_cannot_manage_designs(): void
    {
        $this->actingAs(User::factory()->create());
        $draft = Design::factory()->create(['status' => 'draft', 'title' => 'Draft sample']);
        Design::factory()->create(['status' => 'published', 'title' => 'Published sample']);
        $this->get('/dashboard')->assertOk()->assertDontSee('Draft sample')->assertSee('Published sample')->assertDontSee('Add design');
        $this->get("/designs/{$draft->id}")->assertForbidden();
        $this->get('/dashboard?status=draft')->assertOk()->assertDontSee('Draft sample');
        $this->get('/designs/create')->assertForbidden();
        $this->get("/designs/{$draft->id}/edit")->assertForbidden();
        $this->post('/designs', [])->assertForbidden();
        $this->put("/designs/{$draft->id}", ['title' => 'Changed'])->assertForbidden();
        $this->delete("/designs/{$draft->id}")->assertForbidden();
        $this->assertDatabaseHas('designs', ['id' => $draft->id, 'title' => 'Draft sample']);
    }

    public function test_pagination_uses_24_and_stable_order_and_preserves_filters(): void
    {
        $this->actingAs($this->admin());
        $category = Category::factory()->create();
        $records = Design::factory()->count(25)->create(['category_id' => $category->id, 'status' => 'draft', 'title' => 'Modern card', 'created_at' => '2025-01-01']);
        $params = ['search' => 'Modern', 'category_id' => $category->id, 'status' => 'draft'];
        $response = $this->get('/dashboard?'.http_build_query($params))->assertOk();
        $paginator = $response->viewData('designs');
        $this->assertCount(24, $paginator->items());
        $this->assertSame($records->last()->id, $paginator->items()[0]->id);
        $this->assertSame(25, $paginator->total());
        foreach ($params as $key => $value) {
            $this->assertStringContainsString($key.'='.$value, $paginator->url(2));
        }
        $page2 = $this->get('/dashboard?'.http_build_query($params + ['page' => 2]))->assertOk()->viewData('designs');
        $this->assertCount(1, $page2->items());
        $this->assertSame($records->first()->id, $page2->items()[0]->id);
    }

    public function test_search_and_combined_filters_and_literal_wildcards(): void
    {
        $this->actingAs($this->admin());
        $category = Category::factory()->create();
        $match = Design::factory()->create(['category_id' => $category->id, 'title' => 'تصميم جديد', 'status' => 'published']);
        Design::factory()->create(['category_id' => $category->id, 'title' => 'تصميم قديم', 'status' => 'draft']);
        Design::factory()->create(['title' => 'تصميم آخر', 'status' => 'published']);
        $response = $this->get('/dashboard?'.http_build_query(['search' => 'تصميم', 'category_id' => $category->id, 'status' => 'published']))->assertOk();
        $this->assertSame([$match->id], collect($response->viewData('designs')->items())->pluck('id')->all());
        $literal = Design::factory()->create(['title' => '50%_! Discount']);
        Design::factory()->create(['title' => '50other Discount']);
        $response = $this->get('/dashboard?'.http_build_query(['search' => '50%_!']))->assertOk();
        $this->assertSame([$literal->id], collect($response->viewData('designs')->items())->pluck('id')->all());
        $this->get('/dashboard?search=NoSuchPrefix')->assertOk()->assertSee('No designs found');
        $this->assertSame(5, $this->get('/dashboard')->viewData('designs')->total());
    }

    public function test_admin_can_create_edit_replace_and_delete_uploaded_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $category = Category::factory()->create();
        $this->get('/designs/create')->assertOk();
        $data = ['title' => 'New card', 'category_id' => $category->id, 'status' => 'draft'];
        $this->post('/designs', $data + ['thumbnail' => UploadedFile::fake()->image('card.png')])->assertRedirect();
        $design = Design::firstOrFail();
        $old = $design->thumbnail;
        Storage::disk('public')->assertExists($old);
        $this->get("/designs/{$design->id}/edit")->assertOk();
        $this->put("/designs/{$design->id}", array_replace($data, ['title' => 'Updated', 'status' => 'published']))->assertRedirect();
        $this->assertSame($old, $design->fresh()->thumbnail);
        $this->put("/designs/{$design->id}", $data + ['thumbnail' => UploadedFile::fake()->image('replacement.jpg')])->assertRedirect();
        Storage::disk('public')->assertMissing($old);
        $new = $design->fresh()->thumbnail;
        Storage::disk('public')->assertExists($new);
        $this->delete("/designs/{$design->id}")->assertRedirect('/dashboard');
        Storage::disk('public')->assertMissing($new);
        $this->assertDatabaseMissing('designs', ['id' => $design->id]);
    }

    public function test_placeholder_is_preserved_and_invalid_input_is_not_saved(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('placeholders/design.png', 'shared');
        $this->actingAs($this->admin());
        $category = Category::factory()->create();
        $this->post('/designs', ['title' => '', 'category_id' => 9999, 'status' => 'invalid', 'thumbnail' => UploadedFile::fake()->create('bad.svg', 3, 'image/svg+xml')])->assertSessionHasErrors(['title', 'category_id', 'status', 'thumbnail']);
        $this->assertDatabaseCount('designs', 0);
        $this->post('/designs', ['title' => 'Card', 'category_id' => $category->id, 'status' => 'draft'])->assertSessionHasErrors('thumbnail');
        $this->assertDatabaseCount('designs', 0);
        $design = Design::factory()->create(['thumbnail' => 'placeholders/design.png']);
        $this->assertSame('placeholders/design.png', $design->thumbnail);
        $this->delete("/designs/{$design->id}")->assertRedirect();
        Storage::disk('public')->assertExists('placeholders/design.png');
        $this->get('/designs/999999')->assertNotFound();
    }

    public function test_failed_database_save_cleans_up_new_upload(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $category = Category::factory()->create();
        Design::creating(fn () => throw new \RuntimeException('Simulated save failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post('/designs', ['title' => 'Failed design', 'category_id' => $category->id, 'status' => 'draft', 'thumbnail' => UploadedFile::fake()->image('card.png')]);
            $this->fail('Expected database save failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated save failure', $exception->getMessage());
            $this->assertSame([], Storage::disk('public')->allFiles('designs'));
            $this->assertDatabaseCount('designs', 0);
        } finally {
            Design::flushEventListeners();
        }
    }

    public function test_invalid_update_and_oversized_image_leave_design_unchanged(): void
    {
        $this->actingAs($this->admin());
        $design = Design::factory()->create(['title' => 'Original']);
        $this->put("/designs/{$design->id}", ['title' => str_repeat('a', 256), 'category_id' => $design->category_id, 'status' => 'published', 'thumbnail' => UploadedFile::fake()->image('big.png')->size(2049)])
            ->assertSessionHasErrors(['title', 'thumbnail']);
        $this->assertSame('Original', $design->fresh()->title);
    }
}
