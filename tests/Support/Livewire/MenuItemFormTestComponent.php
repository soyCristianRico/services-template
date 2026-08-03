<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Navigation\MenuItemForm;
use App\Models\MenuItem;
use Livewire\Component;

/**
 * Host component so MenuItemForm can be exercised on its own.
 */
class MenuItemFormTestComponent extends Component
{
    public MenuItemForm $form;

    public function mount(?MenuItem $menuItem = null): void
    {
        if ($menuItem?->exists) {
            $this->form->setMenuItem($menuItem);
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
