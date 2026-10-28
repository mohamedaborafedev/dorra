<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Design;
use Database\Factories\DesignFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DesignSeeder extends Seeder
{
    public function run(int $count = 100000): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Sample designs may only be seeded locally or in tests.');
        }
        if ($count < 1 || $count > 100000) {
            throw new \InvalidArgumentException('Sample count must be between 1 and 100000.');
        }
        $this->call(CategorySeeder::class);
        $ids = Category::orderBy('id')->pluck('id')->all();
        // Top up to 100,000, without deleting or replacing existing data.
        $existing = Design::count();
        $base = strtotime('2024-01-01 00:00:00 UTC');
        for ($offset = $existing; $offset < $count; $offset += 1000) {
            $rows = [];
            for ($i = $offset; $i < min($offset + 1000, $count); $i++) {
                $date = gmdate('Y-m-d H:i:s', $base + (($i * 7919) % 63072000));
                $rows[] = [
                    'category_id' => $ids[$i % count($ids)],
                    'title' => DesignFactory::TITLES[$i % count(DesignFactory::TITLES)].' '.($i + 1),
                    'status' => intdiv($i, 3) % 2 === 0 ? 'draft' : 'published',
                    'thumbnail' => 'placeholders/design.png',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            DB::table('designs')->insert($rows);
        }
    }
}
