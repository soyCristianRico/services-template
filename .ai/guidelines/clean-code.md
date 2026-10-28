# Clean code

- Names carry the intent: a reader should not need a comment to know what a class,
  method or variable is for. English names for everything in code, even when the UI
  text is Spanish (see formatting).
- One responsibility per class and per method. If a method needs "and" to describe
  it, split it. Keep methods short and at one level of abstraction.
- Comments explain *why*, never *what*. Delete commented-out code and dead code.
- No magic values: repeated or meaningful literals become an enum, a constant or config.
- Early returns over nested conditionals. Avoid boolean flag parameters.
- Do not repeat yourself, but do not abstract before the second real use.
- Do not add what nobody asked for (fields, options, layers).
