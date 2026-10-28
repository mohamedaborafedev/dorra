<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Design;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Design> */
class DesignFactory extends Factory
{
    public const TITLES = [
        'Modern Business Card', 'Professional Logo Design', 'Summer Sale Poster',
        'Corporate Branding Template', 'تصميم كارت شخصي', 'شعار شركة احترافي',
        'بوستر عروض الصيف', 'تصميم دعوة زفاف',
    ];

    public function definition(): array
    {
        $created = fake()->dateTimeBetween('2024-01-01', '2026-01-01');

        return [
            'category_id' => Category::factory(),
            'title' => fake()->randomElement(self::TITLES).' '.fake()->numberBetween(1, 999999),
            'status' => fake()->randomElement(['draft', 'published']),
            'thumbnail' => 'placeholders/design.png',
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }
}
