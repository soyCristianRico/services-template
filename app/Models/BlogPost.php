<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Toolkit\Images\Concerns\HasWebImages;

class BlogPost extends Model implements HasMedia
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, HasWebImages, InteractsWithMedia;

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'meta_title',
        'meta_description',
        'author_name',
        'tags',
        'published_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * WebP versions of every image, for <x-toolkit::image> (ruklab/toolkit).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerWebImageConversions();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Publicly visible posts: active, published in the past.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Whether the blog has any publicly visible content. Single source of truth
     * for keeping /blog out of the navigation signals (noindex) and the sitemap
     * while there are no published posts.
     */
    public static function hasPublished(): bool
    {
        return static::query()->published()->exists();
    }
}
