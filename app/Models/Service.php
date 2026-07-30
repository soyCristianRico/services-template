<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Service extends Model implements HasMedia
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'meta_title',
        'meta_description',
        'custom_fields',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Extra categories the service also appears in, beyond its primary `category`.
     */
    public function additionalCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * Read a single custom field value with an optional fallback.
     */
    public function customField(string $key, mixed $default = null): mixed
    {
        return data_get($this->custom_fields, $key, $default);
    }
}
