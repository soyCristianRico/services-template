<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NotFoundLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotFoundLog extends Model
{
    /** @use HasFactory<NotFoundLogFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'hits',
        'first_seen_at',
        'last_seen_at',
        'last_referrer',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
