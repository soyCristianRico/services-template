<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Forms\Catalog\PageForm;
use App\Models\Page;
use Livewire\Component;

/**
 * Host component so PageForm can be exercised on its own.
 */
class PageFormTestComponent extends Component
{
    public PageForm $form;

    public function mount(?Page $page = null): void
    {
        if ($page?->exists) {
            $this->form->setPage($page);
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
