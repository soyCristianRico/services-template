<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Navigation;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class MenuItemForm extends Form
{
    public ?int $id = null;

    public string $location = '';

    public ?int $parent_id = null;

    public string $type = 'link';

    public string $label = '';

    public ?string $url = null;

    public ?string $source = null;

    public int $position = 0;

    public bool $is_active = true;

    public function setMenuItem(MenuItem $menuItem): void
    {
        $this->id = $menuItem->id;
        $this->location = $menuItem->location->value;
        $this->parent_id = $menuItem->parent_id;
        $this->type = $menuItem->type->value;
        $this->label = $menuItem->label;
        $this->url = $menuItem->url;
        $this->source = $menuItem->source;
        $this->position = $menuItem->position;
        $this->is_active = $menuItem->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', Rule::enum(MenuLocation::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id'), $this->parentRule()],
            'type' => ['required', Rule::enum(MenuItemType::class)],
            'label' => ['required', 'string', 'max:120'],
            'url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(https?:\/\/|\/|#|mailto:|tel:)/',
            ],
            // Only the block type answers for its source. A link may still be
            // carrying the one it had before the editor switched type, and
            // `save()` drops it anyway — judging it here would block the change.
            'source' => $this->type === MenuItemType::DynamicBlock->value
                ? ['required', 'string', Rule::in(array_keys(self::blocks()))]
                : ['nullable', 'string'],
            'position' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location.required' => 'Elige en qué menú va el ítem.',
            'label.required' => 'El rótulo es obligatorio.',
            'label.max' => 'El rótulo no puede pasar de 120 caracteres.',
            'url.regex' => 'La dirección tiene que empezar por «/», «http://», «https://», «#», «mailto:» o «tel:».',
            'source.required' => 'Elige qué bloque pinta este ítem.',
            'source.in' => 'Este sitio no sabe pintar ese bloque.',
            'position.min' => 'El orden no puede ser negativo.',
        ];
    }

    public function save(): MenuItem
    {
        $this->validate();

        $isDynamicBlock = $this->type === MenuItemType::DynamicBlock->value;

        $attributes = [
            'location' => MenuLocation::from($this->location),
            'parent_id' => $this->parent_id,
            'type' => MenuItemType::from($this->type),
            'label' => $this->label,
            // Each type owns one of the two fields; carrying the other's leftover
            // would make the item render as something the editor did not pick.
            'url' => $isDynamicBlock ? null : $this->url,
            'source' => $isDynamicBlock ? $this->source : null,
            'position' => $this->position > 0 ? $this->position : $this->nextPosition(),
            'is_active' => $this->is_active,
        ];

        if ($this->id) {
            $menuItem = MenuItem::findOrFail($this->id);
            $menuItem->update($attributes);

            // A submenu belongs wherever its parent hangs: leaving the children
            // behind would strand them in a menu that no longer shows them.
            MenuItem::where('parent_id', $menuItem->id)->update(['location' => $attributes['location']]);
        } else {
            $menuItem = MenuItem::create($attributes);
            $this->id = $menuItem->id;
        }

        $this->url = $menuItem->url;
        $this->source = $menuItem->source;
        $this->position = $menuItem->position;

        return $menuItem;
    }

    /**
     * The blocks this site's layout knows how to paint.
     *
     * @return array<string, string>
     */
    public static function blocks(): array
    {
        /** @var array<string, string> */
        return config('site.menu_blocks', []);
    }

    /**
     * The menu is two levels deep, and a level lives inside one location.
     */
    protected function parentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if ($this->id !== null && (int) $value === $this->id) {
                $fail('Un ítem no puede colgar de sí mismo.');

                return;
            }

            $parent = MenuItem::find($value);

            if ($parent === null) {
                return;
            }

            if ($parent->parent_id !== null) {
                $fail('El padre tiene que ser un ítem de primer nivel: el menú sólo tiene dos niveles.');

                return;
            }

            if ($parent->location->value !== $this->location) {
                $fail('El padre está en otro menú. Elige uno de '.MenuLocation::from($this->location)->label().'.');

                return;
            }

            if ($this->id !== null && MenuItem::where('parent_id', $this->id)->exists()) {
                $fail('Este ítem ya tiene desplegable propio, así que no puede colgar de otro.');
            }
        };
    }

    /**
     * Last slot among the siblings it is about to join, so a new item lands at
     * the end of its menu instead of tying with whatever sits at zero.
     */
    protected function nextPosition(): int
    {
        return (int) MenuItem::query()
            ->where('location', MenuLocation::from($this->location))
            ->where('parent_id', $this->parent_id)
            ->max('position') + 1;
    }
}
