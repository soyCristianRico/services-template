---
name: design-system-from-brief
description: Transform a brand direction brief (text, references, or both) into a structured DESIGN.md at the project root, plus optionally update the Tailwind @theme block in resources/css/app.css. Use when the user asks to create, generate or write a design system, a DESIGN.md, or to "turn this brand brief into a design contract". The brief is typically a markdown document with positioning, colors, typography and tone, but image references and competitor URLs are also accepted.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, WebFetch
disable-model-invocation: true
---

# Design system from brief

Generate a `DESIGN.md` at the project root that acts as the visual contract every future "implement this view" prompt reads first. The input is a brand direction document (color palette, typography, positioning, photography direction, anti-patterns). The output is a structured Markdown file wired to this project's actual stack: Laravel 12 + Livewire 4 + Flux Pro 2 + Tailwind 4.

## When to invoke

Trigger this skill when the user says any of:
- "Genera el DESIGN.md desde este brief"
- "Crea el sistema de diseño con esto"
- "Te paso la dirección de marca, hazme el design.md"
- "Convierte este brief en design contract"
- Equivalent in English

Skip the skill (don't invoke) when:
- The user just wants to tweak colors in `app.css` directly
- There's no brief yet — in that case help them write one first
- A `DESIGN.md` already exists and is current — propose updating it rather than overwriting blindly

## Inputs accepted

1. **Markdown brief** — pasted in the conversation. The most common input. Should contain at least: positioning concept, color palette with hex codes, typography, tone.
2. **Image references** — screenshots, mood boards, logos. Read them multimodally and extract patterns.
3. **Competitor URLs** — use WebFetch to pull the page, then describe the visual language.
4. **Mix of the above** — common when the user has a brief AND a screenshot they like.

If only 1 is provided, that's enough. If the brief is missing critical fields (no color palette, no typography), ask before generating — don't invent core brand decisions.

## Process

### Phase 1 — Confirm the project context

Before writing anything, verify the project the DESIGN.md will live in:

```bash
ls CLAUDE.md composer.json DESIGN.md
```

- If `composer.json` mentions `livewire/flux-pro` → use the Flux Pro + Tailwind 4 mapping below.
- If not → ask the user what stack the project is on before assuming.

Also check if a `DESIGN.md` already exists. If yes, ask whether to overwrite or merge.

**Read the current `resources/css/app.css` before writing anything.** The existing `@theme` block tells you the token names already in use. You are re-valuing an existing system, not inventing one.

**Check the sibling repos.** These projects are clones of one another and live side by side. If a sibling has a `DESIGN.md` and an `app.css`, read them:

```bash
ls -d ../*/DESIGN.md 2>/dev/null
grep -oE '^\s+--color-[a-z-]+' ../<sibling>/resources/css/app.css
```

The **token names are the house convention and stay stable across sites**; only the hex values and roles change per brand. Inventing a new naming scheme for this project is the single most expensive mistake this skill can make — it breaks every pattern copied between repos.

Finally, confirm which token names actually resolve. A token that appears in DESIGN.md but not in `@theme` is a class that silently does nothing.

### Phase 2 — Parse the brief

Extract these atoms from the input (any source):

| Atom | Looks like | Goes into DESIGN.md section |
|---|---|---|
| **Concept / positioning** | "Modern industrial premium, not affiliation, not low-cost" | §1 Concept |
| **Colors** | Hex codes + role (accent, primary, neutrals, semantic) | §3 Color tokens |
| **Typography** | Font family + weights + scale + tracking/letter-spacing | §4 Typography |
| **Spacing / radius** | "Mucho aire", "spacing generoso" | §5 Spacing & radius |
| **Components** | "CTAs amarillos", "cards con poco borde" | §6 Components |
| **Layout** | "Hero protagonista", "secciones full-width" | §7 Layouts |
| **Photography** | "Ciudad nocturna, técnicos, no stock" | §9 Imagery |
| **Tone** | "Premium pero accesible" | §10 Editorial tone |
| **Anti-patterns** | "NO obra barata, NO rayos en el logo" | §11 Anti-patterns |

When the brief uses a CSS variable block (like `@theme { --color-accent: #F5C400; }`), preserve those exact tokens. They become the source of truth for both DESIGN.md and `resources/css/app.css`.

### Phase 3 — Map to the stack

Every visual decision must map to a Flux Pro component or a Tailwind utility.

#### 3a. The Flux `accent` contract — get this right or the brand never applies

This is the highest-leverage part of the whole skill. Flux declares its own `@theme` in `vendor/livewire/flux/dist/flux.css` with **three hardcoded variable names**, defaulting to zinc-800:

```css
--color-accent            /* primary button bg, active/checked fills */
--color-accent-content    /* accent text & icon color (links, active tabs) */
--color-accent-foreground /* text on top of the accent bg */
```

Overriding those three in the project's `@theme` is what tints the entire component library. Verify what they drive in the installed version rather than trusting this list:

```bash
grep -rln "accent" vendor/livewire/flux*/stubs/resources/views/flux/
```

Typically: `flux:button variant="primary"` · `flux:link` · `flux:tab` · `flux:navbar.item` / `flux:navlist.item` active · `flux:checkbox` · `flux:radio` · `flux:switch` · `flux:progress` · `flux:slider` · `flux:calendar` / `flux:date-picker` · `flux:accent` · `flux:timeline`.

Rules that follow, and that **must be written into the generated DESIGN.md** (§3 and §11):

- **The brand's action color goes in `accent`.** Not in `brand`, not in `primary`. If the brief's main color sits anywhere else, every Flux control stays zinc. The brief's *prose* often calls a secondary colour "the accent" — in the codebase `accent` means the action colour Flux reads, so translate rather than copy the word.
- **Never rename the trio.** `--color-primary` or `--color-cta` = Flux ignores it entirely. An invented name is plausible, documented, and resolves to nothing.
- **Always set all three.** Setting `--color-accent` but not `--color-accent-content` leaves links and active tabs zinc while buttons turn brand-colored — an easy-to-miss half-applied theme. Check the sibling repos for exactly this bug before copying their block.
- **Don't document a manual hover** (`hover:bg-accent/90`) on `flux:button`. Flux already darkens the primary ~10% via `color-mix`. Manual hover only applies to custom Blade buttons.
- **Don't pass `color=` to `variant="primary"`.** Each color (`zinc`, `slate`, `gray`, …) redefines the trio locally and overrides the brand.
- **A second brand color needs its own namespace** (`--color-score`, `--color-highlight`, …). It must never occupy `accent`.
- Flux ships a `.dark { --color-accent: white … }` block. In these templates `.dark` is never applied, so it's inert — say so once and forbid new `dark:` variants.

#### 3b. Component mapping

- Buttons → `<flux:button variant="primary|outline|subtle">`, colored by the `accent` trio (see 3a). Not `ghost` for a secondary button — it has no border. Sizes are `base|sm|xs`; **`lg` does not exist and 500s the page**
- Inputs → `<flux:input>`, `<flux:select>`, `<flux:textarea>`
- Headings → `<flux:heading level="1|2|3">` — **`level=`, never `size=`** (see 3c)
- Tables / comparisons → `<flux:table>`
- Badges → `<flux:badge>`
- Cards → no Flux equivalent; custom Blade using `bg-surface` + `border-border`
- Lead capture → `resources/views/components/⚡lead-form` — never re-invent

When the brief calls for something Flux doesn't ship (a Hero block, a score badge), document it as "custom Blade component, no Flux equivalent" and propose a name (`<x-marketing.hero>`, `<x-score-badge>`).

#### 3c. Headings: `level=` vs `size=`

These templates drive the public type scale from `app.css`, keyed off the semantic tag:

```css
[data-public-site] h1[data-flux-heading] { … }
```

`<flux:heading level="1">` renders `<h1 data-flux-heading>` and **picks the scale up**. `<flux:heading size="2xl">` renders a `<div>` and **does not** — it stays at Flux's compact default, so the heading silently ends up tiny. So:

- Public headings → `level=`, with no `text-*` / `font-*` utilities
- Compact headings (modals, forms, banners) → `size=`
- To change a size, edit `app.css`. Never add per-heading `text-*!` overrides.

Check the project's own styling guideline (`.ai/styling` in CLAUDE.md, or `.ai/guidelines/styling.md`) — it usually states this rule, and the generated DESIGN.md must not contradict it.

#### 3d. Icons

Flux's built-in icons are Heroicons. Lucide is available too — `php artisan flux:icon <name>` vendors the SVG from lucide.dev into `resources/views/flux/icon/`. If the brief specifies an icon set, document the command, not just the intent. If the brief says "outline / linear only", forbid Heroicons' `solid` and `micro` variants explicitly.

**Vendoring replaces the Heroicon project-wide, and Lucide has no `solid` variant.** So vendoring an icon that something already renders filled (a star in a rating, a heart in a favourite) breaks that component with *The "solid" variant is not supported in Lucide*. Before documenting a vendored icon, grep for `variant="solid"` on that name; if it's in use, the DESIGN.md must say the filled state comes from `fill-current` over the Lucide outline. Lucide names differ too: `menu`, `x`, `search` — not `bars-3`.

### Phase 4 — Write DESIGN.md

Use the structure below verbatim — every section is required, even if short. Empty sections invite drift; a one-liner ("no decisions yet, default to Flux") is better than missing.

```markdown
# DESIGN.md — <Project name>

> **For AI agents (Claude Code, Cursor, etc.)**: This file is the visual contract for <project>. When generating or modifying any view under `resources/views/pages/**` (public side) or `resources/views/components/**`, follow this file first. The admin (`pages/admin/**`) is internal and not bound by this contract — it uses Flux defaults.
>
> **Source of truth**: brief at `<path-or-link-or-"in-repo">`. Last regenerated <date>.

## 1. Concept & positioning

- One sentence on what the site IS.
- One sentence on what it's NOT (3-5 negatives — "no affiliation, no low-cost, no SEO spam").

## 2. Stack

- Laravel 12 + Livewire 4 (SFC under `resources/views/pages/`, `⚡` prefix)
- Flux Pro v2 — use Flux first; drop to custom Blade only when no equivalent
- Tailwind v4 (CSS-first config via `@theme` in `resources/css/app.css`)
- Public layout: `resources/views/layouts/public.blade.php` (`<body data-public-site>`)
- Icons: `<flux:icon>` (Heroicons built in; Lucide via `php artisan flux:icon <name>`)

## 3. Color tokens

Token **names** are the house convention, shared with the sibling repos; only hex and role change per brand. State which Tailwind family the neutrals come from so derived tints stay in-hue.

### Palette

| Token | Hex | Role | Class usage |
|---|---|---|---|
| `--color-brand` | `#XXXXXX` | identity color | `bg-brand`, `text-brand` |
| `--color-brand-foreground` | `#XXXXXX` | text on `brand` | `text-brand-foreground` |
| `--color-brand-muted` | `#XXXXXX` | soft brand wash — chips, hovers *(derived)* | `bg-brand-muted` |
| `--color-accent` | `#XXXXXX` | **action color — the trio Flux reads** | `bg-accent`, `flux:button variant="primary"` |
| `--color-accent-content` | `#XXXXXX` | accent text/links on light bg | `text-accent-content`, `flux:link` |
| `--color-accent-foreground` | `#XXXXXX` | text ON the accent bg | `text-accent-foreground` |
| `--color-accent-soft` | `#XXXXXX` | accent badge bg *(derived)* | `bg-accent-soft` |
| `<third role>` | `#XXXXXX` | brand's second color, own namespace (`score`, `highlight`, …) | `text-<role>` |
| `<third role>-foreground` | `#XXXXXX` | text on it — check contrast, light hues need dark text | |
| `<third role>-soft` | `#XXXXXX` | its badge bg *(derived)* | |
| `--color-background` | `#XXXXXX` | page bg | `bg-background` |
| `--color-surface` | `#XXXXXX` | cards, header, panels | `bg-surface` |
| `--color-surface-muted` | `#XXXXXX` | alt sections, table headers | `bg-surface-muted` |
| `--color-foreground` | `#XXXXXX` | main text | `text-foreground` |
| `--color-muted-foreground` | `#XXXXXX` | secondary text, meta | `text-muted-foreground` |
| `--color-border` | `#XXXXXX` | default borders | `border-border` |
| `--color-border-strong` | `#XXXXXX` | hover borders, inputs | `border-border-strong` |

If the brand has a single color doing both identity and action, point `brand` and `accent` at the same hex and **say so explicitly** — keeping both names alive means code ported between sibling repos still works.

### ⚠️ Flux: the `accent` trio is mandatory

<Lift the rules from Phase 3a here: the three exact names, what they drive, don't rename,
set all three, no manual hover on flux:button, no `color=` on primary, second color
lives in its own namespace, the `.dark` block is inert.>

### How to use each color
- **`accent`** — DO: CTAs, links, focus rings, active nav. DON'T: large background blocks, full sections, gradients.
- **`<third role>`** — DO: 1-2 elements per view. DON'T: as a second CTA color.
- Golden rule: **one action color per view.**

### `@theme` block (already applied to `resources/css/app.css`)

```css
@theme {
    /* NOTE: do not rename the accent trio — Flux looks for these exact names. */
    --color-accent: #XXXXXX;
    /* ... full block ... */
}
```

### Hygiene rule

Public views use **semantic tokens only**. No raw `text-zinc-*` / `border-gray-*`. When editing a public view that still has raw palette classes, convert them.

### Dark mode

<State once whether `.dark` is ever applied. In these templates it is not — so forbid new `dark:` variants in public views.>

## 4. Typography

### Font family
`<family-name>` via <load method — Bunny Fonts, Google Fonts, self-hosted>. Single family unless explicitly justified.

### Scale & weights

| Level | Use | Class / inline |
|---|---|---|
| H1 hero | One per page max | `font-weight: 700; line-height: 0.95; letter-spacing: -0.03em;` |
| H2 section | | `font-weight: 600; letter-spacing: -0.02em;` |
| H3 card | | `font-weight: 600;` |
| Body | 16px baseline, never Flux's 14px | `font-size: 1rem; font-weight: 400; line-height: 1.6;` |
| Button | | `font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;` (only if brief says so) |

The public type scale lives in **one place**: the `[data-public-site]` rules in `app.css` — `h1|h2|h3[data-flux-heading]` for headings, `[data-flux-text]` for body and `[data-flux-button]` for buttons. To change a size, edit that file — never add per-element `text-*!` / `font-*!` utilities.

**When a heading needs a size the three levels don't give, add a named variant — don't opt out of the system.** The case shows up with region labels: a "Filters" or "Table of contents" heading wants the semantic `h2` without the section scale. Writing it as raw HTML with size utilities gets the look and silently drops that heading out of the scale, so the next change to the type system leaves it behind. Add the variant to the same `app.css` block instead, **named by role and not by page** (`[data-public-site] [data-flux-heading].heading-label`), and use it from the component. One variant serving every region label is a system; one class per page is the utilities problem with extra steps.

**Body and buttons need the same override as the headings, and it is the one that gets forgotten.** Flux ships `flux:text` and `flux:button` at `text-sm` (14px): right for a dense admin UI, too small for a public page, where 16px is the web's default. And it cannot be fixed from the Blade call — Flux prints its own `text-sm` into the element's class list, so `<flux:text class="text-base">` loses to it. The template ships the 16px baseline; a brief that measured something else at the source overrides it in the same block.

### Flux mapping
- `<flux:heading level="1">` → H1 hero (one per page)
- `<flux:heading level="2">` → H2 section
- `<flux:heading level="3">` → H3 card
- `<flux:text>` → body
- **`level=` not `size=`**: `size=` renders a `<div>`, skips the public scale, and stays compact. Reserve it for modals/forms/banners.

Only declare font weights that are actually loaded in the `@import` — a heading asking for 800 when the font loads 400-700 gets faux-bolded by the browser.

## 5. Spacing, radius & elevation

- Spacing scale: <"generous" / "dense" / specific Tailwind tokens used>
- Radius: `rounded-<size>` for <components>
- Shadows: <list of shadow utilities used, or "no shadows — flat design">

## 6. Components

### Buttons
- Primary: `<flux:button variant="primary">` — coloured by the `accent` trio, nothing to add
- Secondary: `<flux:button variant="outline">` — `ghost` renders as bare text with no border
- Sizes: Flux ships `base` / `sm` / `xs` only. **There is no `lg`** — it throws *Unhandled match case* and 500s the page. Size hero CTAs with classes: `class="h-12 px-6 text-base"`
- Same for `<flux:input>`: `size="lg"` doesn't error, it just silently stays at 40px. Use `class:input="h-14! text-base!"` — **`class` on a `flux:input` lands on the wrapper div, not the input**, so it leaves the height untouched and pushes a leading icon out of centre (the icon is `absolute inset-y-0` over the wrapper). The `!` is required too: Flux doesn't wrap height in `:where()`
- No `color=` on primary, no manual `hover:bg-*`, no gradients or shadows (see §11)
- Max one primary button per section

### Inputs (search / lead form)
- `<flux:input>`, `<flux:select>`, `<flux:textarea>` with `class:input="border-border-strong"` — styling the field itself needs `class:input`; plain `class` only reaches the wrapper
- Lead form lives in `resources/views/components/⚡lead-form` — DON'T re-invent; embed via `<livewire:lead-form … />`

### Cards
- `bg-surface` over the page's `bg-background`; the contrast alone separates them
- Padding: `p-6` (list) / `p-8` (featured)
- Border: `border border-border`; hover shifts the border (`hover:border-border-strong`), not the fill
- No Flux equivalent — custom Blade. Extract to `resources/views/components/` once it repeats ≥3 times

### Badges
- Neutral: `<flux:badge color="zinc">`
- Brand highlight: `bg-accent-soft text-accent-content`
- Second-colour signal: `bg-<role>-soft text-<role>-foreground` — 1-2 per view max

## 7. Layouts

### Hero
- Full-width, `min-h-[60vh]` to `[80vh]`
- H1 + subtitle + 1 primary CTA + 0-1 secondary CTA
- Background: <photo-driven, `bg-background` editorial, or solid `bg-brand` with accent details>

### Section
- `py-16 lg:py-24`
- Container `max-w-6xl mx-auto px-6`

### Footer
- <`bg-brand text-brand-foreground` (dark footer) or `bg-surface border-t border-border` (light, editorial) — brief-driven>
- 3 columns desktop, 1 mobile

## 8. Page archetypes

<List the ACTUAL public pages of this project — read `routes/web.php` and
`resources/views/pages/` first; do not assume filenames. Typically:
`⚡home`, `⚡browse` (catch-all category / category×location), `⚡ranking`
(`/mejores-…`), `listing/⚡show` (ficha), `blog/⚡index` + `blog/⚡show`.

For each, give the layout recipe: breadcrumb, H1 shape, what sits above the
fold, whether the lead form is embedded, grid vs list.>

### Domain vocabulary

<The template's models are generic. Map them to what they MEAN in this brand,
as a table — e.g. `Category` → "herramienta", `Listing` → "escuela",
`Offering` → "curso". Agents writing copy need this or the UI reads like the
generic template.>

## 9. Imagery

### DO
- <list from brief — "city at night, technicians, real installations, infrastructure crítica">

### DON'T
- <list from brief — "no stock, no isolated white-bg renders, no fake people">

### Brand assets

Logo, favicon and apple touch icon are produced by the **`brand-assets` skill**, which
owns their paths and sizes. Record only what the brief decided:

- Logo concept, and whether the header uses `logo.png` or `logo.svg`
- Favicon direction: isotype only, no wordmark — it renders small

### OG image (`og:image`)
- 1200×630 at `public/images/og-default.png` — the path is hardcoded in `config/seo.php`, so the file just has to exist under that name
- <description of layout — "dark bg, logo top-left, claim large center, accent stripe">

## 10. Editorial tone

- Voice: <"directo y técnico" / "cercano y profesional" / etc.>
- Vocabulary: prefer <X> over <Y>
- Sentence length: <short / mid>
- CTAs use verbs in imperative ("Pide presupuesto", not "Solicite usted").

## 11. Anti-patterns

What this site explicitly is NOT:
- <list from brief — "afiliación, directorio, SEO spam, low-cost industrial">

Codebase anti-patterns (always include):
- Raw `text-zinc-*` / `text-gray-*` instead of semantic tokens
- `text-*!` / `font-*!` utilities on `flux:heading`
- New `dark:` variants (if `.dark` is never applied)

Flux-specific (breaks the brand silently — always include):
- Renaming `--color-accent` / `--color-accent-content` / `--color-accent-foreground`
- `color=` on `<flux:button variant="primary">`
- `hover:bg-accent/90` on a `flux:button` (duplicates Flux's built-in hover)
- Putting the secondary brand color in `accent`
- `size="lg"` on `flux:button` (500s) or `flux:input` (silently ignored) — size with classes
- plain `class` on a `flux:input` when you meant to style the field — it hits the wrapper; use `class:input`
- `variant="ghost"` as a secondary button — no border; use `outline`
- `solid` / `micro` icon variants when the brief says linear icons, or on any vendored Lucide icon

## 12. Logo

- Concept: <"minimalist geometric monogram" / etc.>
- DON'T use literal industry icons (rayos, enchufes, generators drawn) — those age badly
- Monogram: <initials>, geometric, scalable
- Wordmark: `<NAME>` separated from the isotype

## 13. References & resources

- Brief source: <link/path>
- Inspiration sites: <list from brief>
- Brand color console: <link to a generated palette page if any>
```

### Phase 5 — Optional `app.css` update

After writing DESIGN.md, ask:

> "¿Aplico también el bloque `@theme` a `resources/css/app.css` para que las clases (`bg-accent`, `text-muted-foreground`, la fuente display…) funcionen ya? Sin esto, las clases del DESIGN.md son sólo documentación y el sitio sigue con la identidad anterior."

If yes:
1. Read `resources/css/app.css`
2. Replace the existing `@theme { ... }` block (or add one if missing) with the brief's CSS variables, **keeping the house token names** (Phase 1)
3. Preserve the imports above (`@import 'tailwindcss';`, `@import '...flux.css';`) and the `@custom-variant dark`, `@source` lines below
4. Update the font `@import` to the new families **and only the weights actually used**
5. Update the `[data-public-site] h1|h2|h3[data-flux-heading]` weights if the brief's display font ships different ones
6. Run `npm run build`

**A green build is not proof the theme applied.** A renamed or misspelled token just produces a class that resolves to nothing — nothing errors. Verify the values actually landed:

```bash
grep -o "\-\-color-accent:[^;]*;\|--font-display:[^;]*;" public/build/assets/app-*.css | head
```

Expect the brand hex, not `var(--color-zinc-800)`. If Flux's derived `color-mix` values also show the brand hex, the trio propagated correctly through the component library.

### Phase 6 — Verify

After writing the file:
- Show the user the file path
- Surface the 3-5 most important decisions in a short summary (don't dump the whole file)
- Suggest: "Next: open `resources/views/pages/⚡home.blade.php` and ask me to rebuild the hero following this DESIGN.md"

## Output conventions

- Write to `<project-root>/DESIGN.md` (overwrite if user confirmed earlier)
- Use the project name (from `composer.json` or `APP_NAME` in `.env`) in the title
- Date in the header (today's date, ISO format)
- Spanish content for the actual brand decisions; English for the agent-facing scaffolding comments
- Hex colors in lowercase (`#f5c400` not `#F5C400`) — matches Tailwind convention

## What NOT to do

- **Don't invent palette decisions.** If the brief gives `#F5C400`, use `#f5c400`. Don't expand to "and a complementary purple". Stick to what's documented.
- **But do derive the missing steps.** A brief rarely supplies every `*-soft` / `*-muted` / `*-strong` tint, and the system needs them. Derive from the same Tailwind family the brief's neutrals already belong to (match the hexes against gray/slate/zinc), mark those rows *(derived)* in the table, and note the family. That's filling in a scale, not inventing a color.
- **Don't invent token names.** Reuse whatever the project and its siblings already declare in `@theme`. New names mean dead classes.
- **Don't put the brand's action color anywhere but `accent`.** See Phase 3a — this is the most common way to ship a DESIGN.md that looks right and renders zinc.
- **Don't assume the page filenames.** `ls` the pages directory and read `routes/web.php` before writing §8.
- **Don't write code samples that aren't valid for the stack.** No `border-radius: 4px` when Tailwind 4 expects `rounded-sm`.
- **Don't over-document the admin.** The skill produces a design contract for PUBLIC views. The admin uses Flux defaults — mention it once in §2 and move on.
- **Don't specify logo, favicon or icon sizes.** Those belong to the `brand-assets` skill; duplicating them here guarantees the two drift apart.
- **Don't dump rationale into the file.** Keep DESIGN.md operational: "use this, don't use that". Rationale lives in commit messages and PR descriptions.
