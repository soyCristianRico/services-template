---
name: content-images
description: Detect which content images and graphic assets a page needs (hero photos, section photos, decorative graphics), write the prompt for each, hand them off to be generated outside this repo (ChatGPT, from the ruk-assistant session), then compress them and place them in public/images/. Use before or during page implementation, once DESIGN.md and the page headers/architecture exist, so every hero and section that needs an image has one ready. Does not generate UI icons (Heroicons/Flux UI already covers those) or a photo that must represent one specific real place, building or person — those go through the team's normal image-sourcing process instead.
allowed-tools: Read, Write, Edit, Bash, Glob
disable-model-invocation: true
---

# Content images

Generate the photos and decorative graphics a template needs. Every hero,
feature block and section that carries an image gets one here; UI icons and
photos of a real, identifiable place, building or person do NOT — see "What
this skill does NOT do".

## When to invoke

- "Qué imágenes faltan en esta web", "genera las imágenes que hagan falta"
- Before implementing pages, once DESIGN.md exists and the page headers/
  architecture are known

Skip when: the ask is for a UI icon (the project's icon set already covers
it), or for a photo that must show one specific real place, building or
person (use the team's stock-photo process for that, never generate it).

## Read first

1. `DESIGN.md` at the repo root — palette, mood, typography direction. Every
   generated image has to match it.
2. The page headers/deliverable and the architecture — to know what page
   types and sections exist.
3. `imagenes.md` at the repo root, if it already exists — resume from it
   instead of starting over.

## Requires

Nothing in this repo. **This repo never generates images itself**: no image
API, no key. It decides what is needed and writes the prompt; the images are
generated with ChatGPT from the ruk-assistant session, where the browser
session lives (step 3).

## Process

### 1. List what's needed

Walk every page type (not every published instance) and list, section by
section, where an image or graphic belongs. Write it to `imagenes.md` at the
repo root, one row per entry:

| Dónde va | Qué debe mostrar | Tipo | Proporción / ancho mínimo | Prompt | Alt | Ruta | Estado |
|---|---|---|---|---|---|---|---|

- **Dónde va**: page type and section (`"home, hero"`, `"landing, bloque de
  features"`).
- **Qué debe mostrar**: the concrete content, not a placeholder description.
- **Tipo**: `fotografía realista` or `recurso gráfico` (background shape,
  section divider, texture, supporting illustration). **Never "icono"** — the
  project's icon set (Heroicons via Flux UI) already covers UI icons. If one
  is missing there, that's a custom Flux icon (`resources/views/flux/icon/`),
  not an image.
- **Proporción / ancho mínimo**: the ratio the section needs, plus a minimum
  width — default **1536px for a hero, 1200px for a section photo, 512px for
  a graphic**, unless the design states otherwise.
- **Ruta**: the final path, `public/images/<descriptive-kebab-case>.webp`
  (`home-hero.webp`, `feature-recurso-rapidez.webp`).
- **Prompt** and **Alt** start empty, filled in steps 2 and 5. `Estado`
  starts as `por generar`.

**Scope is per page type, not per instance.** A directory with 200 locations
does not get 200 photos — they share the template's image. A real product or
service photo for a catalog project belongs to the catalog data flow, not
here. **Never generate a photo that must represent one specific real place,
building or person** — flag it in the final report for the team's normal
image-sourcing process instead of faking it.

Decorative graphics are optional: list them only where they add something,
not in every block or every page.

Stop once every section the headers or DESIGN.md mark as a visual block is
covered. A text-only section doesn't get an entry.

### 2. Write each prompt

For each row, build the prompt from: what it must show, the proportion, and
the palette/mood/style pulled from `DESIGN.md` — so it matches the brand
already applied. One self-contained prompt per row, in Spanish, that says
everything: format (horizontal 16:9 for a full-width hero, vertical 4:5 for
half the screen, PNG with transparent background for a loose graphic), what
shows and what the real product looks like, where the page text goes («la
mitad izquierda queda vacía, solo fondo»), background and light in the
palette's colour, and what must not appear («sin texto, sin logotipos, sin
envases, sin manos»). Write it in the `Prompt` column.

### 3. Hand off for generation

Stop and tell the person running this skill, in one message:

> Las imágenes se generan desde ruk-assistant. Abre allí una sesión y pide:
> «genera las imágenes de `<ruta absoluta del repo>/imagenes.md`». Cada una
> se deja en su `Ruta` con extensión `.png`, y su fila pasa a `generada`.
> Avísame cuando estén.

Wait for them. Don't generate, fetch stock or draw placeholders meanwhile.

When they're back, look at each `.png`. **Accept** when it shows what was
asked, respects the proportion and minimum width, and doesn't clash with
`DESIGN.md`'s palette. Otherwise adjust the prompt in `imagenes.md`, set the
row back to `por generar` and hand it off again — **three attempts per image,
no more**. On the third miss, set `Estado = pendiente diseño` on that row and
name it in the final report, with what was asked and what was tried. **This
skill cannot create the ruklab.app action itself** — this repo has no
connection to ruklab.app. Say plainly in the report that an action for the
CEO needs creating with `/acciones` from ruk-assistant (which image, where it
goes, the prompt and what was tried).

The `Prompt` column keeps the prompt that produced the accepted image —
that's what makes it regenerable later without reconstructing it from
memory.

### 4. Compress and place

`cwebp -q 80` (or an equivalent tool) on every accepted `.png`, to its
`Ruta`, and delete the `.png`. Target: under **200 KB** for a hero or
full-section image, under **50 KB** for a small graphic. Set
`Estado = colocada`.

Never render an image wider than the minimum width it was generated at — the
browser only downscales cleanly, it does not upscale without visible loss.
ChatGPT delivers about 1450-1536 px on the long side, which is why a hero's
default minimum is 1536px: if the design asks for more, say so in the report
instead of stretching it.

### 5. Write the alt

One line per row in `Alt`: what the image shows. Empty only if the image is
purely decorative (a background, a shape, a texture with no informational
content). That text is what lands in the page's `alt` attribute once the
page is implemented.

### 6. Report

State plainly, one line per row: placed, pending design (and why, and what
was tried), or skipped as out of scope (a specific real place, a catalog
item, an icon). Anything that needs the team's decision goes in this report
— don't leave it buried as a stale row in `imagenes.md`.

## What this skill does NOT do

- Generate UI icons — the project's icon set (Heroicons via Flux UI) already
  has those.
- Generate a photo that must represent one specific real place, building or
  person — that risks inventing details that don't exist. Use the team's
  normal image-sourcing process for those instead.
- Create the ruklab.app action when an image needs design's help — it only
  names the need in the report; a human creates it from a session connected
  to ruklab.app.
- Generate the images itself, or call any image API — that happens in the
  ruk-assistant session (step 3). Placed images are committed like any other
  static asset in `public/` and deployed normally with the rest of the repo.
