<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'position',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * Takes the services down one by one instead of leaving them to the cascade.
     *
     * The foreign key would delete their rows without Eloquent ever hearing about
     * it, and the images of each one live on a disk under a media row that only
     * fires its own cleanup on a model event. Left to the database, deleting one
     * category strands every photo its services had.
     */
    protected static function booted(): void
    {
        static::deleting(function (Category $category): void {
            $category->services->each->delete();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function descendants(): Collection
    {
        return $this->children->flatMap(fn (self $child) => collect([$child])->merge($child->descendants()));
    }

    public function ancestors(): Collection
    {
        $chain = collect();
        $node = $this->parent;
        while ($node) {
            $chain->push($node);
            $node = $node->parent;
        }

        return $chain;
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Manual display order: lower `position` first, then alphabetical by name as
     * the tie-breaker (so categories left at the default 0 stay alphabetical).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }
}
