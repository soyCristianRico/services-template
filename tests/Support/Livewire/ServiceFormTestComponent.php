<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Catalog\ServiceForm;
use App\Models\Service;
use Livewire\Component;

class ServiceFormTestComponent extends Component
{
    public ServiceForm $form;

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->form->setService($service);
        }
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function rename(): void
    {
        $this->form->rename();
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div></div>
        BLADE;
    }
}
