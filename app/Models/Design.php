<?php

namespace App\Models;

use App\Enums\DesignStatus;
use Database\Factories\DesignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property DesignStatus $status */
class Design extends Model
{
    public function getTableTitleAttribute(): string
    {
        // Only hide the generated sample suffix, preserving real titles such as Logo 2026.
        if ($this->thumbnail === 'placeholders/design.png' || str_starts_with($this->thumbnail ?? '', 'clothing/')) {
            return preg_replace('/^(Modern Business Card|Professional Logo Design|Summer Sale Poster|Corporate Branding Template|تصميم كارت شخصي|شعار شركة احترافي|بوستر عروض الصيف|تصميم دعوة زفاف) [0-9]+$/u', '$1', $this->title) ?? $this->title;
        }

        return $this->title;
    }

    public function getThumbnailUrlAttribute(): string
    {
        if (preg_match('/^clothing\/clothing-(0[1-9]|10)\.jpg$/', $this->thumbnail ?? '')) {
            return asset($this->thumbnail);
        }

        return $this->thumbnail && str_starts_with($this->thumbnail, 'designs/')
            ? asset('storage/'.$this->thumbnail)
            : asset('placeholders/design.png');
    }

    /** @use HasFactory<DesignFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'title', 'status', 'thumbnail'];

    protected function casts(): array
    {
        return ['status' => DesignStatus::class];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
