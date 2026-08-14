<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadChannel;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected $fillable = [
        'landing_id',
        'name',
        'email',
        'phone',
        'message',
        'source_url',
        'channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'click_id',
        'referrer',
        'landing_url',
        'payload',
        'status',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => LeadStatus::class,
            'channel' => LeadChannel::class,
        ];
    }

    public function landing(): BelongsTo
    {
        return $this->belongsTo(Landing::class);
    }

    public function scopeOfStatus(Builder $query, LeadStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', LeadStatus::New);
    }
}
