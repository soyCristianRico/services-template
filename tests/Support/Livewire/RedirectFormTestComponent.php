<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Seo\RedirectForm;
use App\Models\Redirect;
use Livewire\Component;

/**
 * Host component so RedirectForm can be exercised on its own.
 */
class RedirectFormTestComponent extends Component
{
    public RedirectForm $form;

    public function mount(?Redirect $redirect = null): void
    {
        if ($redirect?->exists) {
            $this->form->setRedirect($redirect);
        }
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div></div>
        BLADE;
    }
}
