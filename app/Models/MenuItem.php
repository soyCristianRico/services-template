<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id', 'location', 'type', 'label', 'url', 'source', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'type' => MenuItemType::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeIn(Builder $query, MenuLocation $location): Builder
    {
        return $query->where('location', $location)->orderBy('position');
    }

    /**
     * Top-level items only — children are reached through `children`.
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
