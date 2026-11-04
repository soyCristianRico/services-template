<?php

declare(strict_types=1);

namespace App\Notifications\Lead;

use App\Channels\DiscordChannel;
use App\Models\Lead;
use App\Notifications\Messages\DiscordMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LeadCapturedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(public Lead $lead) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(object $notifiable): DiscordMessage
    {
        $this->lead->loadMissing(['landing.category', 'landing.location']);

        $message = DiscordMessage::create()
            ->content('Ha llegado un nuevo lead desde la web.')
            ->title('Nuevo lead: '.$this->lead->name, url: route('admin.leads.show', $this->lead))
            ->info()
            ->field('Teléfono', $this->lead->phone ?: '-', true)
            ->field('Email', $this->lead->email, true)
            ->field('Origen', $this->landingLabel(), true);

        if (is_string($this->lead->message) && $this->lead->message !== '') {
            $message->field('Mensaje', $this->lead->message);
        }

        return $message
            ->footer($this->lead->source_url ?: 'Sin URL de origen')
            ->timestamp($this->lead->created_at);
    }

    protected function landingLabel(): string
    {
        $landing = $this->lead->landing;

        if ($landing === null) {
            return 'Contacto directo';
        }

        $category = $landing->category->name ?? null;
        $location = $landing->location?->name;

        return implode(' - ', array_filter([$category, $location])) ?: 'Landing';
    }
}
