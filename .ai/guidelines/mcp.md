# MCP tools

## Parameters

- The site's MCP server registers `Toolkit\Mcp\CallToolRejectingUnknownParameters` in its
  `boot()`. Every call is checked against the tool's `schema()` before the tool runs: an
  argument the schema does not declare is refused by name ("did you mean ...?") and nothing
  runs. Without it, `laravel/mcp` drops the argument and the call still reports success
  (ruklab, 6/11/2026: a task created from a template with `target_date` came out with no date).
- So a tool never reads, validates or `prohibited`s an argument it does not declare: the
  caller can no longer send one. To keep a field out of reach, leave it out of `schema()`.
- When callers keep reaching for a name the tool does not use (`tab` for `sheet`), implement
  `Toolkit\Mcp\SuggestsParameterAliases` so the error points to the right one.
- A new MCP server registers the method in its own `boot()`, like the existing one.

## Tests

- A test for a field the tool must not take sends it and expects it refused:
  `->assertHasErrors(['Unknown parameter: "price"'])`, then asserts nothing changed.
- `schema()` runs on every call now, as it does for `tools/list`: a schema that throws
  breaks every call to that tool. Cover it with a test that describes every tool on the
  server (`app($tool)->toArray()`).
