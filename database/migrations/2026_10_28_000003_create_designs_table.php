<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->string('thumbnail')->nullable();
            $table->timestamps();
            // Ascending B-trees support reverse scans for DESC, DESC ordering.
            // Create this before the FK so InnoDB can reuse its leftmost prefix.
            $table->index(['category_id', 'status', 'created_at', 'id'], 'idx_designs_category_status_created');
            $table->index('title', 'idx_designs_title');
            $table->index(['created_at', 'id'], 'idx_designs_created_id');
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE designs ADD CONSTRAINT chk_designs_status CHECK (status IN ('draft', 'published'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
