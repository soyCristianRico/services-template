<?php

use App\Livewire\Forms\Lead\LeadForm;
use App\Models\Landing;
use App\Services\Lead\LeadService;
use Livewire\Component;

new class extends Component
{
    public ?Landing $landing = null;

    public string $sourceUrl = '';

    public LeadForm $form;

    public bool $submitted = false;

    public function mount(?Landing $landing = null): void
    {
        $this->landing = $landing;
        $this->sourceUrl = $landing ? url('/'.$landing->slug) : request()->url();
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
