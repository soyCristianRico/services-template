# Blade views

- **No `@php` in a view.** The logic lives in the component and the view only lays it out:
  a Livewire component works it out in a `#[Computed]` property (or a method); an anonymous
  Blade component receives its data ready as props, or becomes a class component
  (`php artisan make:component`) when it needs logic of its own.
- The same goes for type checks and `match` blocks repeated through a view: move them to the
  component, or to the enum when two screens ask the same question.
- `tests/Unit/BladeViewsTest.php` fails on any new `@php`. The views that already had one when
  the rule arrived are listed there, and the list only shrinks: when you touch one of them, move
  its logic out and take it off the list (the test also fails if a listed view no longer has it).
