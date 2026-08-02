# Testing

## Folder Structure
Pattern: `tests/Feature/{ClassType}/{Module}/` (type first, then module — Catalog, Blog, Lead, Seo, etc.)
- `tests/Feature/Commands/Catalog/`, `tests/Feature/Jobs/Lead/`, etc.
- `tests/Feature/Models/`, `tests/Feature/Policies/` → shared, not by module

## Required Syntax
Use Pest with `describe()` + `it()`. NEVER use `test()`.

```php
describe('ClassName', function () {
    describe('method_name', function () {
        it('should do something', function () { });
    });
});
```

## Execution
- The suite runs on MySQL, from `.env.testing` (see `.ai/guidelines/database.md`).
  `php artisan test` runs all of it in well under a minute — the old "never run
  the full suite, it runs out of memory" rule was an artefact of SQLite
  `:memory:` and no longer applies.
- While working, still run the narrowest thing that proves the change:
  `php artisan test --compact tests/Feature/ExampleTest.php` or `--filter=testName`.
  Run the whole suite before calling a task done.

## Rules
- One test file per class; add `describe()` blocks to existing files
- Search before creating: `find tests -name "*Name*Test.php"`
- Flux UI modals/toasts: test state changes, not visual behavior
- First `describe()` = class name, nested `describe()` = method name
- NEVER put `it()` directly under the class-level `describe()`

## Test Maintenance
When modifying logic:
1. Search for existing tests covering the modified functionality
2. Update existing tests to reflect new behavior
3. Create new tests if none exist
4. Run related tests after changes

## Browser Tests (Pest 4)
- Location: `tests/Browser/`
- Use for JavaScript/Alpine functionality requiring real browser interaction
- Use `@data-test` selectors: `->click('@resolve-feedback')` (maps to `data-test="resolve-feedback"`)

## Database Errors During Tests
- If you see "database is being recreated", "table not found", "database locked" or similar: **tests are likely running in another terminal**
- Wait for the other process to finish before running your tests
- Check for other running test processes before assuming a real DB problem

## Livewire Form Tests
- When creating a Form class (`app/Livewire/Forms/`), MUST create its test
- Host component in `tests/Support/Livewire/{FormName}TestComponent.php`
- Test file in `tests/Feature/Forms/{Module}/{FormName}Test.php`
- Test: initialization, validation (`rules()` or `#[Validate]`), save methods, `updated()` hook
