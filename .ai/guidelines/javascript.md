# JavaScript & Alpine

## Alpine Component Structure

```javascript
Alpine.data('componentName', (serverData) => ({
    // Data (from server)
    items: serverData || [],

    // State (client-side)
    chart: null,
    isOpen: false,
    filter: Alpine.$persist('all').as('component-filter'),

    // Getters
    get filteredItems() { ... },

    // Lifecycle
    init() { ... },
    destroy() { ... },

    // Public: Actions
    toggle(id) { ... },

    // Public: Formatters
    formatNumber(val) { ... },

    // Private
    _initChart() { ... },
    _bindEvents() { ... },
}));
```

Section order: Data → State → Getters → Lifecycle → Public Actions → Public Formatters → Private (`_` prefix).

## Non-Reactive Data

Store library instances outside Alpine's reactive data to avoid proxy issues:

```javascript
Alpine.data('component', () => {
    let editorInstance = null;
    return {
        init() { editorInstance = this.$refs.editor.getEditor(); },
    };
});
```

## Flux Editor Auto-Save Pattern

Use Alpine with `$wire` instead of `wire:model` for `flux:editor` with debounced saving. Use `:value="$form->body"` on the component. Listen to `flux:editor:ready`, store the editor instance outside reactive data, and debounce `$wire.saveBody(html)` on editor `update` events.

## Optimistic UI Pattern

Update UI immediately, rollback on failure:

```javascript
async saveItem() {
    const original = this.item.text;
    this.item.text = newText;
    const success = await $wire.save(newText);
    if (!success) {
        this.item.text = original;
        Flux.toast({ text: 'Error message', variant: 'warning' });
    }
}
```

## Instant UI Feedback

A click that only shows, hides or counts something on screen must not wait for the
server: on a slow connection a `wire:model.live` round trip makes the screen feel
broken. Reach for this whenever the answer is already in the browser.

- Keep the input deferred (`wire:model`, no `.live`) when the server does not need the
  value until an action runs; the value travels with that action.
- Show and hide in the browser: Alpine state (`x-show`, `x-text`) for state Alpine owns,
  or a CSS `:has()` rule on the state Flux already puts in the DOM (`data-checked`,
  `aria-expanded`...).
- Use `.live` only when the server has to answer each change: a filter that reloads the
  list, a select that depends on another, a count that comes from the database.
- When the change is a write, save in the background with the optimistic pattern above.

**`$wire.<array>` filled by `wire:model` on a checkbox group does not wake Alpine
effects**: `x-show="$wire.selected.length > 0"` never updates (Livewire 4.3, seen in the
fees list of cierzo-formacion). Show the bulk actions with CSS instead:

```blade
<div class="group/list space-y-6">
    <div class="hidden group-has-[[data-flux-checkbox][data-checked]]/list:flex">
        <flux:button wire:click="markSelectedPaid">Marcar como pagadas</flux:button>
    </div>

    <flux:checkbox wire:model="selected" value="{{ $row->id }}" />
</div>
```

## Component Communication
- Dispatch: `window.dispatchEvent(new CustomEvent('event-name', { detail: { data } }))`
- Listen: `window.addEventListener('event-name', (e) => { ... })`
- Use `$watch()` to react to state changes

## External JS Modules
- Place in `resources/js/`, import in `app.js`
- Use `_method()` for protected methods (not `#method` - causes context issues)
- Export public API via `window` for Alpine access

## Selectors
- Use `data-` attributes for JS hooks, not classes
- Extract repeated selectors to constants
