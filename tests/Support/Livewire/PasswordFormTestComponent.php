<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Account\PasswordForm;
use Livewire\Component;

class PasswordFormTestComponent extends Component
{
    public PasswordForm $form;

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
