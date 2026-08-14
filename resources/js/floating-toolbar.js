/**
 * Floating formatting toolbar for the admin's rich text fields.
 *
 * The toolbar is fixed-positioned over the current selection and hidden as soon
 * as the selection collapses, so a long description reads as plain copy while
 * you write and only offers formatting once there is something to format.
 *
 * Any Flux editor gets it by rendering `<flux:editor.toolbar>` with the
 * `.floating-toolbar` class — the listener at the bottom of this file wires it
 * up, so the Blade side only has to declare the markup. That is what
 * `<x-admin.rich-editor>` does.
 */

const TOOLBAR_SELECTOR = '.floating-toolbar';

/** Gap between the top of the selection and the bottom of the toolbar. */
const TOOLBAR_OFFSET = 48;

/** Breathing room kept between the toolbar and the edges of the viewport. */
const VIEWPORT_MARGIN = 8;

/**
 * Find the floating toolbar inside `root` and keep clicking it from stealing
 * the editor's selection — a lost selection is a button that formats nothing.
 */
function findToolbar(root) {
    const toolbar = root.querySelector(TOOLBAR_SELECTOR);

    if (toolbar && ! toolbar.__armed) {
        toolbar.__armed = true;
        toolbar.addEventListener('mousedown', (e) => e.preventDefault());
    }

    return toolbar;
}

/** Reveal `toolbar` over the editor's selection and hide it on blur. */
function bindToolbar(editor, toolbar) {
    if (! editor || ! toolbar || toolbar.__bound) {
        return;
    }

    toolbar.__bound = true;

    editor.on('selectionUpdate', () => positionToolbar(toolbar, editor));
    editor.on('blur', () => setTimeout(() => hideToolbar(toolbar), 150));
}

function hideToolbar(toolbar) {
    toolbar?.classList.remove('is-visible');
}

/**
 * Where a `position: fixed` toolbar measures its coordinates from.
 *
 * Normally that is the viewport, but a transformed ancestor becomes the
 * containing block instead — and a Flux modal keeps an identity transform on
 * its `<dialog>` after animating in. Viewport coordinates have to be rebased
 * against it or the toolbar lands away from the selection inside every modal.
 */
function fixedOrigin(toolbar) {
    for (let parent = toolbar.parentElement; parent; parent = parent.parentElement) {
        const style = getComputedStyle(parent);

        if (style.transform !== 'none'
            || style.perspective !== 'none'
            || style.filter !== 'none'
            || style.backdropFilter !== 'none'
            || style.willChange !== 'auto') {
            const rect = parent.getBoundingClientRect();

            return { left: rect.left, top: rect.top };
        }
    }

    return { left: 0, top: 0 };
}

/**
 * Center the toolbar above the current selection, or hide it when there is
 * nothing selected to format.
 *
 * Coordinates come from ProseMirror rather than `window.getSelection()`: the
 * DOM selection is synced asynchronously, so it still reads as collapsed while
 * the `selectionUpdate` handler runs.
 */
function positionToolbar(toolbar, editor) {
    if (! toolbar || ! editor) {
        return;
    }

    const { from, to, empty } = editor.state.selection;

    if (empty) {
        hideToolbar(toolbar);

        return;
    }

    const view = editor.view;
    const start = view.coordsAtPos(from);
    const end = view.coordsAtPos(to);
    const sameLine = Math.abs(start.top - end.top) < 1;
    const contentRect = view.dom.getBoundingClientRect();

    // A selection spanning several lines has no meaningful midpoint, so the
    // toolbar centers over the block instead.
    const left = sameLine
        ? (start.left + end.left) / 2
        : contentRect.left + contentRect.width / 2;

    const halfWidth = toolbar.offsetWidth / 2;
    const origin = fixedOrigin(toolbar);

    toolbar.style.top = (Math.min(start.top, end.top) - TOOLBAR_OFFSET - origin.top) + 'px';
    toolbar.style.left = (Math.min(
        Math.max(left, halfWidth + VIEWPORT_MARGIN),
        window.innerWidth - halfWidth - VIEWPORT_MARGIN,
    ) - origin.left) + 'px';
    toolbar.classList.add('is-visible');
}

/**
 * Every editor that renders the floating toolbar gets wired up on its own, on
 * the event Flux fires once Tiptap is up. It survives `wire:navigate` because
 * it is bound to the document and not to the page being replaced.
 */
document.addEventListener('flux:editor:ready', (e) => {
    const editor = e.detail?.editor;
    const root = e.target;

    if (! editor || ! (root instanceof Element)) {
        return;
    }

    bindToolbar(editor, findToolbar(root.closest('[data-flux-editor]') ?? root));
});
