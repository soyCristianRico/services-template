# Formatting

- Run `composer run format` before completing any task (Rector + Pint)
- Add imports manually, the formatter organizes them automatically
- Fallback: `vendor/bin/pint`

## Language
- **Code**: comments, documentation, commit messages, variable names, function names, class names → English
- **User-visible text**: labels, titles, buttons, placeholders, error messages, toasts, enum labels → Spanish
- This includes Blade templates, Livewire components, and any text shown in the UI
- The spec and the client speak Spanish, the code does not: translate every business term once, in the spec's glossary, and use only that English name in models, tables, columns, routes, enums (case names included) and tests. Never carry a Spanish word from the spec into an identifier.
- Enum `label()` methods should return Spanish text (e.g., "Borrador" not "Draft")
