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
        $lead = Lead::create([
            ...$this->attribution(),
            ...$attributes,
            'status' => $attributes['status'] ?? LeadStatus::New,
        ]);

        $this->notify($lead);

        return $lead;
    }

    /**
     * De dónde venía la visita que acaba de convertir.
     *
     * Va aquí y no en el formulario a propósito: es el único sitio por el que
     * pasan todas las capturas, así que ninguna puede olvidarse de anotarlo.
     *
     * Vuelve vacío cuando no hay sesión que preguntar, que es el caso de todo
     * lead que nazca fuera de una visita. Esos se quedan sin canal, y sin canal
     * es la respuesta honesta: no llegaron por ningún sitio que hayamos visto.
     *
     * Lo que traiga el llamante manda sobre lo de la sesión, no al revés.
     *
     * @return array<string, string|null>
     */
    protected function attribution(): array
    {
        return LeadAttribution::fromSession()?->toLeadAttributes() ?? [];
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
