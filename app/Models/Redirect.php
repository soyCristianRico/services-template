<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RedirectMatchType;
use App\Enums\RedirectStatusCode;
use App\Services\Seo\RedirectResolver;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'destination',
        'match_type',
        'status_code',
        'is_active',
        'preserve_query',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => RedirectMatchType::class,
            'status_code' => RedirectStatusCode::class,
            'is_active' => 'boolean',
            'preserve_query' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The whole table is cached and read on every request, so any change from
        // the panel has to drop it. Hits are counted with a query-builder update
        // precisely so they do not land here and flush the cache on every visit.
        static::saved(static fn () => static::flushCache());
        static::deleted(static fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(RedirectResolver::CACHE_KEY);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
