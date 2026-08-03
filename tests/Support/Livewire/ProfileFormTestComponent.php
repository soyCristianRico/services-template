<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Account\ProfileForm;
use Livewire\Component;

class ProfileFormTestComponent extends Component
{
    public ProfileForm $form;

    public function mount(): void
    {
        $this->form->setUser(auth()->user());
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
