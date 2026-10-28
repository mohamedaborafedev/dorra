<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Business Cards', 'Logos', 'Posters', 'Flyers', 'Brochures', 'Invitations', 'Social Media', 'Packaging', 'Banners', 'Certificates'] as $name) {
            Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
