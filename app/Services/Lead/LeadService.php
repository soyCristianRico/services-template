<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Enums\LeadStatus;
use App\Mail\Lead\NewLeadMail;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Lead\LeadCapturedNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class LeadService
{
    /**
     * Persist a lead and fire post-capture side effects (email, Discord).
     *
     * The caller is responsible for input validation (typically via the Lead
     * form Livewire component) — this method assumes well-formed data.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function capture(array $attributes): Lead
    {
        $lead = Lead::create([...$attributes, 'status' => $attributes['status'] ?? LeadStatus::New]);

        $this->notify($lead);

        return $lead;
    }

    protected function notify(Lead $lead): void
    {
        $emails = $this->resolveNotifyEmails();
        if ($emails !== []) {
            Mail::to($emails)->queue(new NewLeadMail($lead));
        }

        if (is_string(config('services.discord.webhook_url')) && config('services.discord.webhook_url') !== '') {
            Notification::route('discord', null)->notify(new LeadCapturedNotification($lead));
        }
    }

    /**
     * Resolve the lead notification recipients.
     *
     * Falls back to the first registered user (typically the site owner) when
     * LEAD_NOTIFY_EMAIL is not configured, so notifications are never silently
     * lost on a fresh deploy.
     *
     * @return list<string>
     */
    protected function resolveNotifyEmails(): array
    {
        $configured = config('leads.notify_email');

        if (is_string($configured) && trim($configured) !== '') {
            return str($configured)
                ->explode(',')
                ->map(fn (string $email): string => trim($email))
                ->filter()
                ->values()
                ->all();
        }

        $owner = User::oldest('id')->value('email');

        return $owner === null ? [] : [$owner];
    }
}
