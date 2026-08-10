---
name: page-from-design
description: Build a brand-new page from an approved design export (HTML, screenshots, assets) plus its content document, using the components and tokens the project already has. For pages that exist nowhere yet — the team designed them from scratch and there is no source site to compare against.
disable-model-invocation: true
allowed-tools: Read, Write, Edit, Bash, Glob, Grep, WebFetch, mcp__playwright__browser_navigate, mcp__playwright__browser_snapshot, mcp__playwright__browser_evaluate, mcp__playwright__browser_take_screenshot
---

# Page from an approved design

Implement **a page nobody has seen yet**: the team designs it, it arrives as an export
(HTML, screenshots, assets) and its real copy comes in a separate document. There is no
source site to compare against, and that is the whole difference with cloning an
existing page: there the source is the truth, here **the truth is split across three
papers that contradict each other** — the design, the content document and the ticket.

Input: design folder + content document + ticket. Output: the page built, its content
persisted where this project persists content, and verified at both widths.

## 1 — Read all three papers before writing a line

The content document may arrive as a file, a Google Doc or a deliverable URL. Read it
whole first; the ticket's summary of it is not it.

**The design export ships more than what you see.** Besides the HTML and the
screenshots there are usually `assets/`, `uploads/` and hidden state files. Open them
all: an image-slot state file can carry the good photo in base64, and `uploads/` the PDF
the ticket only linked to.

**The design's text is filler until proven otherwise.** It was written to fill the
mockup, not to be published, and it can state false things about a real person:
degrees, job titles, career. **The content document wins**, always, and that has to be
said on delivery — whoever approved the design may not have read that small print.

**The number of slots is not the number of items.** If the mockup paints five topics
and three figures, and the document brings six topics and two figures, six and two go
in. An empty slot is removed; **a spare slot is not filled by inventing**.

Note the contradictions and show them on delivery instead of resolving them silently:
whoever wrote the content and whoever drew the mockup are usually different people and
neither knows they disagree.

## 2 — Translate to the system, don't copy the markup

`DESIGN.md` is the design contract and `resources/css/app.css` holds the tokens. Pull
the export's colors, sizes and typefaces and **look for them in both**. If they match,
the design was made with the system and no new CSS is needed. If they don't, it is a
decision to ask about, not a value to paste — and if the answer is that the contract was
wrong, fix `DESIGN.md` in the same pass and say what changed, or the next page repeats
the error.

Layout translates the same way: the export's fixed container is whatever container the
rest of the pages already use, and its section spacing is their vertical rhythm. Read a
neighbouring page and reuse those classes; don't transcribe the export's pixels.

**Pixel-perfect is about content and hierarchy, not markup.** What has to be nailed is
the order of the sections, the heading scale, the vertical rhythm and the density. What
does not is a button's padding or a border's width, if that forces you out of the
components.

**And no new component to nail a detail.** A hand-written `<a>` with the design's
classes looks identical today and is outside the system tomorrow: it inherits neither
the radius change, nor the typography one, nor the color one. If the system's component
has no such variant, use the closest one **and adjust it with classes on top**. Stepping
outside the box is the client's call, not the implementer's: raise it and wait.

## 3 — Read the component's docs before writing it

Before using a Flux component, look at its page on `fluxui.dev/components/{name}`. It
costs a minute and saves the whole detour. What you learn there is the shape the
component expects, and it is almost always shorter than the one you improvise:

- Icons go in by attribute —`icon="linkedin"`, `icon:trailing="chevron-down"`—, not by
  slot. The slot is only needed when the trailing element is not an icon but another
  component.
- Sizes are `base`, `sm` and `xs`. **There is no large one**, which is why a taller CTA
  needs an explicit height class. Put it where the site already puts it, not on every
  button — two conventions for the same button is worse than the gap Flux leaves.
- `href` turns a button into a link, nothing else needed.

**Headings always go through `flux:heading level="1|2|3"` with no `text-*`/`font-*`
classes.** The scale lives in one place, `app.css`, scoped to the public site. A design
export invites the opposite — a raw `<h2>` with size utilities gets the look and quietly
drops that heading out of the scale. If a heading needs a size the three levels don't
give, add a **named variant by role, not by page**, to that same file and use it from
the component.

**An icon Heroicons doesn't have** —any brand: LinkedIn, WhatsApp— is declared as a Flux
custom icon in `resources/views/flux/icon/{name}.blade.php`, with the template Flux
documents, and requested by name like theirs. Flux registers `resources/views/flux` as
its own components and that is why it works. A parallel `x-icon.*` does the same thing
worse: it doesn't accept `icon=` and leaves two drawings that drift apart.

When adjusting an icon, **don't tighten its `viewBox` to the stroke by eye**: it clips
the edge and the symptom —a chewed-up letter— shows up far from the cause.

## 4 — Look at the assets before deciding the frame

**Measure the original's real resolution** (`getimagesize`) before fixing the size it is
painted at. A 300×300 portrait in a 400×480 frame looks bad, and no mockup fixes that:
the browser scales it up, and a scaled-up photo looks worse the larger it is painted.

The rule is that the browser **only ever downscales**. If the source doesn't reach,
there are two honest ways out —lower the frame to what the source can take, or ask for
the good asset— and both get said. Neither of them is keeping quiet.

When the asset is good, **crop it to the design's ratio when saving** instead of letting
`object-cover` crop a square source: the crop gets decided once and seen, instead of
depending on whatever height the container has that day.

If the file feeds more than one page —a portrait read by both an author card and a team
section—, **warn that the change shows up in both**. And if the new photo is of a
different person than the one that was there, say so: it can be exactly what was wanted
or the wrong file, and from here they look the same.

## 5 — Build before you look

`npm run build` **before the first screenshot**. A Tailwind class that didn't exist
before is not in the compiled CSS, and the symptom is that the mockup ignores the grid
or the size: you go looking for the bug in the Blade, which is fine, for half an hour.
This applies to every new batch of classes, not just the first.

## 6 — Verify by measuring

Capture desktop and mobile, and **measure with `browser_evaluate`** instead of trusting
your eye: background color, text color, the four borders, button height, the icon's
vertical offset from center. Half the mismatches the eye forgives show up there, and
some the eye reports turn out to be something else.

Two traps with screenshots:

- **A full-page capture lies about `lazy` images**: they come out as grey blocks and
  look broken. Before chasing the bug, check in the HTML that the URL is there.
- **A centered element can still look off**, because what gets centered is its box, not
  its ink. If the geometry says zero offset and it still looks crooked, the problem is
  inside the drawing.

Check too that there is no horizontal overflow on mobile.

## 7 — Route, meta and persistence

**Before creating a route or a component, look at what the project already resolves.**
There is usually a catch-all `/{slug}` and a component behind it that already covers
this case; the normal move is to adjust it, not to write one in parallel. And **two
routes with the same method and URI don't coexist**: Laravel indexes them by that key,
the last one wins and the first disappears without warning.

**Meta has no source here.** With no origin site, title, description, canonical and OG
come from the content document or they get asked for. They are not deduced from the
headline.

**Whatever the page reads from the database has to be reproducible by a seeder.** What
only lives in the local database never reaches production: deploys run seeders, they
don't copy the database. Follow the project's convention — designed pages usually live
in Blade and only their editable text goes to the database; if the project has a content
seeder, the page's copy goes in its data file and the seeder gets re-run to check the
file reproduces what is in the database.

If the content document brings internal links written absolute and with a trailing
slash, **store them relative and without it**. It is invisible —Laravel trims the slash
when routing, so the link works when clicked— and it leaves an article whose internal
links all point at an address neither the canonical nor the sitemap recognizes. If the
project ships a link-normalizing command, that check is already written.

On delivery, state in one line each thing that departed from the design and why, and
what was left out of the ticket for lack of material.

## Guardrails

- **The system beats the design.** If the design forces a new component or loose CSS,
  raise it; it doesn't get decided while laying out.
- **Don't invent content**: not the figure missing from the card, not the spare topic,
  not the degree the design made up.
- **No image is painted larger than its original.**
- **No custom component duplicates a Flux one.** If Flux has to be extended, extend it
  where Flux says.
- Comment only what can't be deduced by reading the code, and where it belongs.
- The header and the footer are not part of the page: if the design changes them, that
  is a different job.
- Before calling the page done, run the tests for what was touched and the project's
  formatter (`composer run format`, or `vendor/bin/pint --dirty`).
