# DESIGN.md — services-template

> **For AI agents (Claude Code, Cursor, etc.)**: this is the template's placeholder
> design contract. It is **not** a brand: every site cloned from this template
> replaces this file wholesale with its own.
>
> Generate the real one with `/design-system-from-brief` (from a brand brief) or
> `/services-extract-design` (from the live site being cloned). Both write this
> file and the `@theme` block in `resources/css/app.css` together.

## Until it is regenerated

The `@theme` block in `resources/css/app.css` ships a deliberately neutral slate
palette so a fresh checkout renders without looking like anybody in particular.
It is a placeholder, not a brand — replace it.

> This template used to ship a previous client's palette, logo and design contract.
> A fresh clone therefore started out wearing someone else's brand until it was
> regenerated. If you find any other leftover of that kind, remove it.

## What survives regeneration

Two things are house convention and must stay stable across every site, because
markup gets copied between the sibling repos:

**Token names.** `--color-brand{,-foreground,-muted}`, the `--color-accent` trio,
`--color-{background,surface,surface-muted,foreground,muted-foreground,border,border-strong}`,
`--font-{sans,display}`, `--radius-*`, `--shadow-*`, `--spacing-*`. Only the hex
values and the roles change per brand. Inventing a new naming scheme breaks every
pattern ported between repos.

**The Flux accent trio.** Flux reads three literal names — `--color-accent`,
`--color-accent-content`, `--color-accent-foreground` — and defaults them to
zinc-800. The brand's *action* colour goes there, not in `brand`. Set all three:
defining only `--color-accent` tints buttons while links and active tabs stay zinc,
which is easy to miss and has already shipped in more than one site in this family.

## Brand assets

- Logo at `public/images/logo.png`. The public layout falls back to the site name
  when the file is absent, so a fresh clone never shows a broken image.
- Favicon at `public/favicon.png`, OG image at `public/images/og-default.png`
  (1200×630), declared in `.env` as `SEO_DEFAULT_IMAGE`.
