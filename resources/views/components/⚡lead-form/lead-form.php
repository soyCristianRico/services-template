<?php

use App\Livewire\Forms\Lead\LeadForm;
use App\Models\Landing;
use App\Services\Lead\LeadService;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public ?Landing $landing = null;

    #[Locked]
    public string $sourceUrl = '';

    public LeadForm $form;

    public bool $submitted = false;

    /**
     * Embedded without a landing, the container still hands `mount()` an empty,
     * unsaved Landing instead of null, so only a stored one counts as context.
     */
    public function mount(?Landing $landing = null): void
    {
        $this->landing = $landing?->exists ? $landing : null;
        $this->sourceUrl = $this->landing ? url('/'.$this->landing->slug) : request()->url();
    }

    public function save(LeadService $service): void
    {
        $isHoneypotTripped = filled($this->form->website);

        $this->form->submit($this->landing, $this->sourceUrl, $service);

        if (! $isHoneypotTripped) {
            $this->pushLeadEventToDataLayer();
        }

        $this->submitted = true;
    }

    /**
     * Tell GTM a real lead was captured (it listens for `lead_form_submitted`).
     * Only the source URL travels: never the lead's name, email or phone.
     */
    protected function pushLeadEventToDataLayer(): void
    {
        $this->js(
            'window.dataLayer = window.dataLayer || []; '
            .'window.dataLayer.push('.json_encode([
                'event' => 'lead_form_submitted',
                'lead_source_url' => $this->sourceUrl,
            ]).');'
        );
    }
};
