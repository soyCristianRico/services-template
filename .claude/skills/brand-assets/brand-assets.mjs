#!/usr/bin/env node
/**
 * Brand asset processor.
 *
 * Turns raw logo / app-icon exports (the kind image generators produce: flat
 * background baked in, or an alpha channel with holes punched through the
 * artwork) into the transparent, tightly cropped PNGs the public layout uses.
 *
 * sharp only decodes, resizes and encodes. Keying, trimming and mask rebuilding
 * are done here, because the interesting failures live in the pixels.
 *
 *   node brand-assets.mjs inspect <file>
 *   node brand-assets.mjs logo <src> --out public/logo.png [--width 400]
 *   node brand-assets.mjs icon <src> --out public/favicon.png [--size 40]
 *   node brand-assets.mjs preview <file...> --out check.png
 */

import sharp from 'sharp';

const NOISE_FLOOR = 3; // alpha below this is background noise, not artwork

/* ---------------------------------------------------------------- loading */

async function loadRgba(path) {
    const image = sharp(path).ensureAlpha();
    const { data, info } = await image.raw().toBuffer({ resolveWithObject: true });
    return { width: info.width, height: info.height, data };
}

function at(img, x, y) {
    const i = (y * img.width + x) * 4;
    return [img.data[i], img.data[i + 1], img.data[i + 2], img.data[i + 3]];
}

async function writePng(img, out) {
    await sharp(Buffer.from(img.data), { raw: { width: img.width, height: img.height, channels: 4 } })
        .png({ compressionLevel: 9 })
        .toFile(out);
}

/* ------------------------------------------------------------ background */

/** Median colour of the border ring, plus how much it varies across it. */
function borderColour(img) {
    const samples = [];
    const stepX = Math.max(1, Math.floor(img.width / 60));
    const stepY = Math.max(1, Math.floor(img.height / 60));

    for (let x = 0; x < img.width; x += stepX) {
        samples.push(at(img, x, 0), at(img, x, img.height - 1));
    }
    for (let y = 0; y < img.height; y += stepY) {
        samples.push(at(img, 0, y), at(img, img.width - 1, y));
    }

    const transparent = samples.filter(([, , , a]) => a < 8).length / samples.length;
    const median = [0, 1, 2].map((c) => {
        const values = samples.map((s) => s[c]).sort((a, b) => a - b);
        return values[values.length >> 1];
    });
    const spread = Math.max(...[0, 1, 2].map((c) => {
        const values = samples.map((s) => s[c]);
        return Math.max(...values) - Math.min(...values);
    }));

    return { colour: median, spread, transparent };
}

/** Distance of a pixel from the background colour, ignoring its alpha. */
function distance(px, bg) {
    return Math.max(Math.abs(px[0] - bg[0]), Math.abs(px[1] - bg[1]), Math.abs(px[2] - bg[2]));
}

/**
 * The handful of solid colours the artwork is actually drawn in.
 *
 * Knowing them lets every edge pixel be treated as a known colour blended with
 * the background, which recovers an exact alpha instead of a guessed one.
 */
function detectPalette(img, bg, { maxColours = 4, minDistance = 60, merge = 40 } = {}) {
    // Only pixels whose neighbours match them: anti-aliased edges are blends of
    // two colours and would otherwise seed clusters of their own.
    const histogram = new Map();
    const step = Math.max(1, Math.floor(Math.sqrt((img.width * img.height) / 200000)));

    for (let y = 1; y < img.height - 1; y += step) {
        for (let x = 1; x < img.width - 1; x += step) {
            const px = at(img, x, y);
            if (px[3] < 200 || distance(px, bg) < minDistance) {
                continue;
            }
            const flat = [at(img, x - 1, y), at(img, x + 1, y), at(img, x, y - 1), at(img, x, y + 1)]
                .every((n) => n[3] >= 200 && distance(px, n) <= 12);
            if (!flat) {
                continue;
            }
            const key = px.slice(0, 3).map((c) => c >> 3).join(',');
            const bin = histogram.get(key) ?? { sum: [0, 0, 0], n: 0 };
            bin.sum = bin.sum.map((v, i) => v + px[i]);
            bin.n++;
            histogram.set(key, bin);
        }
    }

    const bins = [...histogram.values()]
        .map((bin) => ({ colour: bin.sum.map((v) => Math.round(v / bin.n)), n: bin.n }))
        .sort((a, b) => b.n - a.n);

    // Most populated bin first, skipping anything close to a colour already taken.
    const palette = [];
    for (const bin of bins) {
        if (palette.length >= maxColours) {
            break;
        }
        if (!palette.some((c) => distance(bin.colour, c) < merge)) {
            palette.push(bin.colour);
        }
    }

    return palette;
}

/**
 * Alpha of `px` assuming it is `ref` composited over `bg`, with the fit error.
 *
 * Both the pixel and the reference are expressed as their offset from the
 * background: that offset keeps its direction whatever the alpha is, so its
 * projection onto the reference offset *is* the alpha.
 */
function unmix(px, ref, bg) {
    const v = [bg[0] - px[0], bg[1] - px[1], bg[2] - px[2]];
    const r = [bg[0] - ref[0], bg[1] - ref[1], bg[2] - ref[2]];
    const norm = r[0] ** 2 + r[1] ** 2 + r[2] ** 2;
    if (norm === 0) {
        return { alpha: 0, error: Infinity };
    }
    const alpha = (v[0] * r[0] + v[1] * r[1] + v[2] * r[2]) / norm;
    const error = Math.hypot(...v.map((c, i) => c - alpha * r[i]));
    return { alpha, error };
}

/** Replace a flat background with transparency, recovering anti-aliased edges. */
function keyBackground(img, bg, palette, { fitTolerance = 40 } = {}) {
    const out = { width: img.width, height: img.height, data: Buffer.alloc(img.width * img.height * 4) };
    let fallback = 0;

    for (let i = 0; i < img.width * img.height; i++) {
        const s = i * 4;
        const px = [img.data[s], img.data[s + 1], img.data[s + 2]];
        if (distance(px, bg) <= 2) {
            continue;
        }

        let best = null;
        for (const ref of palette) {
            const fit = unmix(px, ref, bg);
            if (!best || fit.error < best.error) {
                best = { ...fit, colour: ref };
            }
        }

        let { alpha, colour } = best ?? { alpha: 0, colour: null };
        if (!best || best.error > fitTolerance) {
            // Not a blend of any known brand colour: un-premultiply generically.
            fallback++;
            alpha = Math.max(...px.map((c, k) => Math.abs(c - bg[k]))) / 255;
            colour = px.map((c, k) => Math.round((c - (1 - alpha) * bg[k]) / (alpha || 1)));
        }

        const a = Math.round(Math.min(1, Math.max(0, alpha)) * 255);
        if (a < NOISE_FLOOR) {
            continue;
        }

        out.data[s] = Math.min(255, Math.max(0, colour[0]));
        out.data[s + 1] = Math.min(255, Math.max(0, colour[1]));
        out.data[s + 2] = Math.min(255, Math.max(0, colour[2]));
        out.data[s + 3] = a;
    }

    return { image: out, fallback };
}

/* -------------------------------------------------------------- geometry */

function alphaBounds(img, threshold = 1) {
    let minX = img.width;
    let minY = img.height;
    let maxX = -1;
    let maxY = -1;

    for (let y = 0; y < img.height; y++) {
        for (let x = 0; x < img.width; x++) {
            if (img.data[(y * img.width + x) * 4 + 3] >= threshold) {
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                maxY = y;
            }
        }
    }

    return maxX < 0 ? null : { x: minX, y: minY, w: maxX - minX + 1, h: maxY - minY + 1 };
}

function crop(img, box) {
    const out = { width: box.w, height: box.h, data: Buffer.alloc(box.w * box.h * 4) };
    for (let y = 0; y < box.h; y++) {
        const from = ((box.y + y) * img.width + box.x) * 4;
        img.data.copy(out.data, y * box.w * 4, from, from + box.w * 4);
    }
    return out;
}

function insideRounded(px, py, w, h, radius) {
    const cx = Math.min(Math.max(px, radius), w - radius);
    const cy = Math.min(Math.max(py, radius), h - radius);
    return (px - cx) ** 2 + (py - cy) ** 2 <= radius * radius;
}

/**
 * Fit a rounded rectangle to the artwork: bounding box plus the corner radius,
 * read off as the distance it takes each edge to reach its full extent.
 */
function measureRoundedRect(img, isSubject) {
    const rowSpan = (y) => {
        let first = -1;
        let last = -1;
        for (let x = 0; x < img.width; x++) {
            if (isSubject(x, y)) {
                if (first < 0) first = x;
                last = x;
            }
        }
        return first < 0 ? null : [first, last];
    };
    const colSpan = (x) => {
        let first = -1;
        let last = -1;
        for (let y = 0; y < img.height; y++) {
            if (isSubject(x, y)) {
                if (first < 0) first = y;
                last = y;
            }
        }
        return first < 0 ? null : [first, last];
    };

    const rows = [];
    for (let y = 0; y < img.height; y++) {
        const span = rowSpan(y);
        if (span) rows.push([y, span]);
    }
    if (!rows.length) {
        throw new Error('no artwork found: the whole image reads as background');
    }

    const x0 = Math.min(...rows.map(([, s]) => s[0]));
    const x1 = Math.max(...rows.map(([, s]) => s[1]));
    const y0 = rows[0][0];
    const y1 = rows[rows.length - 1][0];

    const radiusY = rows.find(([, s]) => s[0] <= x0 + 1)?.[0] - y0;
    const firstFullColumn = (() => {
        for (let x = x0; x <= x1; x++) {
            const span = colSpan(x);
            if (span && span[0] <= y0 + 1) return x;
        }
        return x0;
    })();

    const radius = Math.max(1, Math.round(((radiusY || 0) + (firstFullColumn - x0)) / 2));
    const box = { x: x0, y: y0, w: x1 - x0 + 1, h: y1 - y0 + 1 };

    // How much of the fitted shape the artwork actually fills, so a caller can
    // tell a genuine app icon from something that is not rectangular at all.
    let covered = 0;
    for (let y = box.y; y < box.y + box.h; y++) {
        for (let x = box.x; x < box.x + box.w; x++) {
            if (isSubject(x, y)) covered++;
        }
    }

    return { ...box, radius, coverage: covered / (box.w * box.h) };
}

function roundedMask(size, radius, samples = 4) {
    const mask = new Float32Array(size * size);
    const step = 1 / samples;

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            let hits = 0;
            for (let sy = 0; sy < samples; sy++) {
                for (let sx = 0; sx < samples; sx++) {
                    if (insideRounded(x + (sx + 0.5) * step, y + (sy + 0.5) * step, size, size, radius)) {
                        hits++;
                    }
                }
            }
            mask[y * size + x] = hits / (samples * samples);
        }
    }

    return mask;
}

/**
 * Box-filter the icon rectangle down to `size`, taking colour only from well
 * inside the shape. The source alpha is deliberately ignored — generated icons
 * often have holes punched through the artwork — so the outline comes purely
 * from the mask rebuilt at the target size.
 */
function resampleIcon(img, rect, radius, size, inset) {
    const out = { width: size, height: size, data: Buffer.alloc(size * size * 4) };
    const missing = [];

    const valid = (sx, sy) => insideRounded(
        sx + 0.5 - inset, sy + 0.5 - inset,
        rect.w - 2 * inset, rect.h - 2 * inset,
        Math.max(radius - inset, 1),
    );

    for (let ty = 0; ty < size; ty++) {
        const sy0 = Math.floor((ty * rect.h) / size);
        const sy1 = Math.max(Math.floor(((ty + 1) * rect.h) / size), sy0 + 1);

        for (let tx = 0; tx < size; tx++) {
            const sx0 = Math.floor((tx * rect.w) / size);
            const sx1 = Math.max(Math.floor(((tx + 1) * rect.w) / size), sx0 + 1);

            let r = 0;
            let g = 0;
            let b = 0;
            let n = 0;

            for (let sy = sy0; sy < sy1; sy++) {
                for (let sx = sx0; sx < sx1; sx++) {
                    if (!valid(sx, sy)) continue;
                    const s = ((rect.y + sy) * img.width + rect.x + sx) * 4;
                    r += img.data[s];
                    g += img.data[s + 1];
                    b += img.data[s + 2];
                    n++;
                }
            }

            const d = (ty * size + tx) * 4;
            if (n) {
                out.data[d] = Math.round(r / n);
                out.data[d + 1] = Math.round(g / n);
                out.data[d + 2] = Math.round(b / n);
                out.data[d + 3] = 255;
            } else {
                missing.push([tx, ty]);
            }
        }
    }

    // The outer ring has no colour of its own yet. Each pixel takes the colour
    // found straight towards the centre; borrowing from neighbours instead
    // would smear one edge's colour along the whole rim.
    const resolved = Uint8Array.from({ length: size * size }, (_, i) => out.data[i * 4 + 3]);
    const centre = size / 2;

    for (const [tx, ty] of missing) {
        let vx = centre - (tx + 0.5);
        let vy = centre - (ty + 0.5);
        const length = Math.hypot(vx, vy) || 1;
        vx /= length;
        vy /= length;

        for (let step = 0; step <= length; step += 0.5) {
            const nx = Math.floor(tx + 0.5 + vx * step);
            const ny = Math.floor(ty + 0.5 + vy * step);
            if (nx < 0 || ny < 0 || nx >= size || ny >= size || !resolved[ny * size + nx]) {
                continue;
            }
            out.data.copy(out.data, (ty * size + tx) * 4, (ny * size + nx) * 4, (ny * size + nx) * 4 + 4);
            break;
        }
    }

    return out;
}

/* -------------------------------------------------------------- commands */

async function inspect(src) {
    const img = await loadRgba(src);
    const bg = borderColour(img);

    const alphas = new Map();
    for (let i = 3; i < img.data.length; i += 4) {
        const bucket = img.data[i] === 0 ? 0 : img.data[i] === 255 ? 255 : 128;
        alphas.set(bucket, (alphas.get(bucket) ?? 0) + 1);
    }

    console.log(`file       ${src}`);
    console.log(`size       ${img.width}x${img.height}`);
    console.log(`alpha      opaque ${alphas.get(255) ?? 0} · partial ${alphas.get(128) ?? 0} · clear ${alphas.get(0) ?? 0}`);
    console.log(`border     rgb(${bg.colour}) · spread ${bg.spread} · ${Math.round(bg.transparent * 100)}% transparent`);

    const hasAlpha = bg.transparent > 0.9;
    const isSubject = hasAlpha
        ? (x, y) => at(img, x, y)[3] >= 128
        : (x, y) => distance(at(img, x, y), bg.colour) > 24;

    const rect = measureRoundedRect(img, isSubject);
    console.log(`artwork    ${rect.w}x${rect.h} at ${rect.x},${rect.y} · radius ~${rect.radius} · fills ${Math.round(rect.coverage * 100)}% of its box`);

    if (hasAlpha) {
        let holes = 0;
        for (let y = rect.y; y < rect.y + rect.h; y++) {
            for (let x = rect.x; x < rect.x + rect.w; x++) {
                const inner = insideRounded(x - rect.x + 0.5 - 6, y - rect.y + 0.5 - 6, rect.w - 12, rect.h - 12, Math.max(rect.radius - 6, 1));
                if (inner && at(img, x, y)[3] < 128) holes++;
            }
        }
        console.log(`holes      ${holes} transparent px inside the artwork${holes ? ' — source alpha is unreliable, rebuild the mask' : ''}`);
    } else {
        console.log(`palette    ${detectPalette(img, bg.colour).map((c) => `#${c.map((v) => v.toString(16).padStart(2, '0')).join('')}`).join(' ')}`);
    }

    console.log(rect.coverage > 0.85
        ? '\nlooks like an app icon → use the `icon` command'
        : '\nlooks like free-standing artwork → use the `logo` command');
}

async function buildLogo(src, out, width) {
    const img = await loadRgba(src);
    const bg = borderColour(img);
    let working = img;
    let note = 'source already transparent';

    if (bg.transparent <= 0.9) {
        if (bg.spread > 24) {
            throw new Error(`background is not flat (spread ${bg.spread}); it must be keyed by hand`);
        }
        const palette = detectPalette(img, bg.colour);
        if (!palette.length) {
            throw new Error('no artwork found against the background');
        }
        const keyed = keyBackground(img, bg.colour, palette);
        working = keyed.image;
        note = `keyed rgb(${bg.colour}) · palette ${palette.map((c) => `#${c.map((v) => v.toString(16).padStart(2, '0')).join('')}`).join(' ')} · ${keyed.fallback} px off-palette`;
    }

    const box = alphaBounds(working);
    if (!box) {
        throw new Error('everything was keyed away; the background guess was wrong');
    }
    const trimmed = crop(working, box);

    await sharp(Buffer.from(trimmed.data), { raw: { width: trimmed.width, height: trimmed.height, channels: 4 } })
        .resize({ width, kernel: 'lanczos3' })
        .png({ compressionLevel: 9 })
        .toFile(out);

    const height = Math.round((trimmed.height * width) / trimmed.width);
    console.log(`${out}  ${img.width}x${img.height} → trimmed ${trimmed.width}x${trimmed.height} → ${width}x${height}`);
    console.log(`  ${note}`);
    console.log(`  cropped left ${box.x} · top ${box.y} · right ${img.width - box.x - box.w} · bottom ${img.height - box.y - box.h}`);
}

async function buildIcon(src, out, size, { inset = 3, rect: forced, radius: forcedRadius, square = false } = {}) {
    const img = await loadRgba(src);
    const bg = borderColour(img);
    const hasAlpha = bg.transparent > 0.9;
    const isSubject = hasAlpha
        ? (x, y) => at(img, x, y)[3] >= 128
        : (x, y) => distance(at(img, x, y), bg.colour) > 24;

    if (forced && forcedRadius === undefined) {
        throw new Error('--rect overrides the fitted shape, so --radius must be given too');
    }

    const measured = forced ?? measureRoundedRect(img, isSubject);
    const radius = forcedRadius ?? measured.radius;
    const ratio = radius / measured.w;

    const resampled = resampleIcon(img, measured, radius, size, inset);

    // A square icon keeps the artwork bleeding into the corners: iOS masks the
    // home-screen icon itself, and transparent corners come out fringed.
    if (!square) {
        const mask = roundedMask(size, ratio * size);
        for (let i = 0; i < size * size; i++) {
            resampled.data[i * 4 + 3] = Math.round(mask[i] * 255);
        }
    }

    await writePng(resampled, out);
    console.log(`${out}  ${img.width}x${img.height} → icon ${measured.w}x${measured.h} at ${measured.x},${measured.y} (radius ${radius}) → ${size}x${size}${square ? ' square, full bleed' : ' rounded'}`);
    if (Math.abs(measured.w - measured.h) > 2) {
        console.log(`  note: source icon is ${measured.w}x${measured.h}, squared to fit`);
    }
}

/** Contact sheet on light and dark, so halos and fringes are visible. */
async function preview(sources, out) {
    const images = await Promise.all(sources.map(loadRgba));
    const cell = Math.max(...images.map((i) => Math.max(i.width, i.height)), 64);
    const zoom = images.map((i) => Math.max(1, Math.floor(cell / Math.max(i.width, i.height))));
    const pad = 24;
    const width = pad + images.reduce((sum, img, k) => sum + img.width * zoom[k] + pad, 0);
    const rowHeight = cell + pad * 2;
    const data = Buffer.alloc(width * rowHeight * 2 * 4);

    const paint = (row, x, y, colour) => {
        const d = ((row * rowHeight + y) * width + x) * 4;
        data[d] = colour[0];
        data[d + 1] = colour[1];
        data[d + 2] = colour[2];
        data[d + 3] = 255;
    };

    [[255, 255, 255], [24, 24, 27]].forEach((bg, row) => {
        for (let y = 0; y < rowHeight; y++) {
            for (let x = 0; x < width; x++) {
                paint(row, x, y, bg);
            }
        }

        let cursor = pad;
        for (let k = 0; k < images.length; k++) {
            const img = images[k];
            const z = zoom[k];
            const top = Math.floor((rowHeight - img.height * z) / 2);

            for (let y = 0; y < img.height * z; y++) {
                for (let x = 0; x < img.width * z; x++) {
                    const px = at(img, Math.floor(x / z), Math.floor(y / z));
                    const a = px[3] / 255;
                    paint(row, cursor + x, top + y, [0, 1, 2].map((c) => Math.round(px[c] * a + bg[c] * (1 - a))));
                }
            }
            cursor += img.width * z + pad;
        }
    });

    await writePng({ width, height: rowHeight * 2, data }, out);
    console.log(`${out}  ${sources.length} asset(s) over light and dark`);
}

/* ------------------------------------------------------------------ main */

function parseArgs(argv) {
    const positional = [];
    const flags = {};
    for (let i = 0; i < argv.length; i++) {
        if (!argv[i].startsWith('--')) {
            positional.push(argv[i]);
            continue;
        }
        const next = argv[i + 1];
        const takesValue = next !== undefined && !next.startsWith('--');
        flags[argv[i].slice(2)] = takesValue ? argv[++i] : true;
    }
    return { positional, flags };
}

const [command, ...rest] = process.argv.slice(2);
const { positional, flags } = parseArgs(rest);

try {
    if (command === 'inspect') {
        await inspect(positional[0]);
    } else if (command === 'logo') {
        await buildLogo(positional[0], flags.out ?? 'public/logo.png', Number(flags.width ?? 400));
    } else if (command === 'icon') {
        const rect = flags.rect
            ? (([x, y, w, h]) => ({ x, y, w, h }))(String(flags.rect).split(',').map(Number))
            : undefined;
        await buildIcon(positional[0], flags.out ?? 'public/favicon.png', Number(flags.size ?? 40), {
            inset: Number(flags.inset ?? 3),
            rect,
            radius: flags.radius ? Number(flags.radius) : undefined,
            square: flags.square === true,
        });
    } else if (command === 'preview') {
        await preview(positional, flags.out ?? 'preview.png');
    } else {
        console.error('usage: brand-assets.mjs <inspect|logo|icon|preview> <src...> [--out path] [--width 400] [--size 40] [--square]');
        process.exit(1);
    }
} catch (error) {
    console.error(`error: ${error.message}`);
    process.exit(1);
}
