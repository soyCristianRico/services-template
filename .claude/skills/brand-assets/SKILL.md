---
name: brand-assets
description: Turn raw logo and app-icon exports into the site's real assets — strip the baked-in background, crop to the artwork, and write public/images/logo.png at 400px wide and public/favicon.png at 40x40 (this template's layouts reuse the favicon as the apple-touch-icon; see the output contract). Use when the user hands over a logo and/or favicon file ("aquí tienes el logo", "quítale el fondo y recórtalo", "actualiza el favicon", "here's the new logo") and expects them installed in the project. Handles the usual generated-image defects: flat background instead of transparency, and an alpha channel with holes punched through the artwork.
---

# Brand assets

The user drops one or two image paths — a **logo** (wordmark, free-standing) and
an **app icon** (the rounded square used as favicon), usually straight out of an
image generator and much larger than needed. This skill cleans them, resizes
them and installs them.

## Output contract

Fixed, do not improvise sizes or paths:

| Asset | Path | Size |
|-------|------|------|
| Logo | `public/images/logo.png` | **400px wide**, height follows the artwork |
| Favicon | `public/favicon.png` | **40x40**, rounded corners |

This template's layouts (`public.blade.php`, `admin.blade.php`, `auth.blade.php`)
point `<link rel="apple-touch-icon">` at the same `favicon.png` instead of a
dedicated 180x180 file — sibling templates (directory-template, rental-template)
do build a separate `public/apple-touch-icon.png`. Don't add one here on your
own initiative; that requires also changing the three `<link>` tags, which is a
template-wiring decision, not an asset-generation one. Flag it to the user
instead.

Open Graph (`public/images/og-default.png`, 1200x630) is **not** produced here —
it comes from the designer.

## Workflow

Run everything from the project root. The job is not done until step 4.

### 1. Inspect before processing

```bash
node .claude/skills/brand-assets/brand-assets.mjs inspect <src>
```

It reports the size, the alpha breakdown, the background colour, the artwork's
bounding box and corner radius, and whether the source alpha is trustworthy. It
ends by naming the command the file needs. **Read that output first** — it is
what tells you which failure mode you are dealing with.

For a background-baked-in source it also prints the detected palette. Sanity
check it against what you can see in the image: everything downstream is derived
from it.

### 2. Build

```bash
node .claude/skills/brand-assets/brand-assets.mjs logo <src> --out public/images/logo.png --width 400
node .claude/skills/brand-assets/brand-assets.mjs icon <src> --out public/favicon.png --size 40
```

`logo` keys out the flat background and crops to the artwork with zero padding.
`0 px off-palette` in its output means every pixel was explained by the detected
palette. `icon` rebuilds the rounded-square mask from scratch and ignores the
source alpha.

Override flags when the automatic fit is wrong: `--rect x,y,w,h` **and**
`--radius n` together (a `--rect` without a `--radius` produces a blank icon),
and `--inset n` (default 3) for how far inside the shape colour is sampled.

Source icons are rarely exactly square; the command squares them and says so. A
3-4% difference is imperceptible — anything larger, ask for a better source.

### 3. Verify visually — do not skip

```bash
node .claude/skills/brand-assets/brand-assets.mjs preview public/images/logo.png public/favicon.png --out /tmp/check.png
```

Then **read the generated file**. It composites the assets over white and over
near-black. Only the dark row is informative:

- **A pale fringe around the artwork** = the background was not flat and the
  edges kept their white. Not acceptable — go to "When it fails".
- **Dark blotches inside the icon** = the source had transparent regions with
  no colour underneath. Not acceptable — go to "When it fails".
- **Colour that disappears into the dark row** = fine and expected when the
  artwork has a near-black colour. It is proof the alpha is exact.

### 4. Wire it into the layout

Check `resources/views/layouts/public.blade.php` points at the two files: the
header `<img>` at `images/logo.png` (behind the `file_exists` fallback — see
`DESIGN.md`), and `<link rel="icon">` / `<link rel="apple-touch-icon">` at
`favicon.png`. Fix the references if they differ — do not rename the outputs
to match an old reference.

## When it fails

Do not hand over a broken asset. Each error has one next move:

- **`background is not flat (spread N)`** — the source has a gradient or a photo
  behind the artwork; this script cannot key it. Ask the user to regenerate the
  asset on a flat background or with real transparency.
- **`no artwork found` / `everything was keyed away`** — the background guess was
  wrong, usually because the artwork touches the image border. Ask for a version
  with margin.
- **Pale fringe in the preview** — same cause as the first case. Regenerate the
  source; do not paper over it by raising a tolerance.
- **Wrong crop or a lopsided icon** — retry with `--rect` + `--radius` read off
  the `inspect` output. If two attempts do not fix it, the source is the problem.
- **Dark blotches or dark corners in the icon** — the source's transparent
  pixels carry no colour, so there is nothing to recover there. Ask for the icon
  re-exported flattened, with no transparency at all; `icon` builds its own mask
  and does not need the source alpha.

## Notes

- Generated app icons often have transparent pixels *inside* the artwork.
  `inspect` counts them ("holes … source alpha is unreliable"), which is why
  `icon` derives the **outline** from a freshly computed mask instead of trusting
  the source alpha. That fixes the rim only: a hole keeps whatever colour the
  source stored under it, so a hole exported with no colour underneath comes out
  as a dark blotch. That is what step 3 is looking for. Never patch it by filling
  holes from neighbouring pixels — it smears one edge's colour along the whole
  rim; get a flattened source instead.
- `sharp` (devDependency) does decoding, resizing and encoding, so any input
  format works — PNG, JPG, WebP, AVIF.
- If the user supplies an **SVG** logo, do not rasterise it: adjust its `viewBox`
  to the artwork bounds instead, keep it vector, and point the layout at the
  `.svg`.
