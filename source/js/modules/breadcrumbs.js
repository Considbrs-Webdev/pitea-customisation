/**
 * Mobile breadcrumb handling
 *
 * The Municipio theme (at ≤32.5em / 520px) hides all breadcrumb items except
 * the direct parent (nth-last-child(2)). This makes sense with a back-arrow
 * icon, but Piteå uses a rotated horizontal-rule as a slash separator, so the
 * "show only parent" pattern looks broken and provides no navigation context.
 *
 * This module overrides that behaviour for breadcrumbs with 4+ items:
 *   - Replaces "Start" with a house icon (screen-reader text preserved)
 *   - Collapses middle items into a single "…" expand button
 *   - Clicking "…" reveals the full path and focuses the first collapsed item
 *
 * WCAG notes:
 *   - Collapsed items are hidden from AT with aria-hidden="true"; the current
 *     page (aria-current="page") always remains visible; the direct parent
 *     also remains visible unless there are only 3 items total (in which case
 *     it too is collapsed so the "…" button is meaningful)
 *   - The expand button carries aria-expanded and aria-label in Swedish
 *   - The home icon uses aria-hidden="true"; the text "Start" lives in a
 *     visually-hidden span that is always in the DOM for screen readers
 */

// Matches the Municipio theme's breakpoint for breadcrumb collapsing
const MOBILE_MQ = window.matchMedia('(max-width: 32.5em)');

export function initMobileBreadcrumbs() {
    document.querySelectorAll('nav.c-breadcrumb').forEach(setupBreadcrumb);
}

function setupBreadcrumb(nav) {
    const list = nav.querySelector('.c-breadcrumb__list');
    if (!list) return;

    const items = Array.from(list.querySelectorAll(':scope > .c-breadcrumb__item'));
    if (!items.length) return;

    // Always enhance the home/first item with an icon
    setupHomeIcon(items[0]);

    // Only apply collapse behaviour when there are enough items to warrant it
    // (home + direct parent + current = 3 minimum)
    if (items.length < 3) return;

    nav.classList.add('c-breadcrumb--js-controlled');

    const expandLi = buildExpandItem(items[1]);
    items[0].after(expandLi);

    let expanded = false;

    function applyState() {
        if (!MOBILE_MQ.matches) {
            // Always show everything on wider viewports and reset state
            expanded = false;
            expandAll(items, expandLi);
        } else if (expanded) {
            expandAll(items, expandLi);
        } else {
            collapseMiddle(items, expandLi);
        }
    }

    expandLi.querySelector('button').addEventListener('click', () => {
        expanded = true;
        applyState();
        // Move focus to the first revealed middle item for keyboard users
        items[1]?.querySelector('a, button')?.focus();
    });

    MOBILE_MQ.addEventListener('change', applyState);
    applyState();
}

function collapseMiddle(items, expandLi) {
    // Keep first item, expand button, and the last N items visible.
    // With 4+ items the direct parent (second-to-last) stays visible;
    // with exactly 3 items (home + parent + current) the parent is also
    // collapsed so the "…" button is meaningful.
    const keepFromEnd = items.length <= 3 ? 1 : 2;
    const middleItems = items.slice(1, items.length - keepFromEnd);
    middleItems.forEach(li => {
        li.setAttribute('aria-hidden', 'true');
        li.style.display = 'none';
    });
    expandLi.removeAttribute('hidden');
    expandLi.querySelector('button').setAttribute('aria-expanded', 'false');
}

function expandAll(items, expandLi) {
    items.forEach(li => {
        li.removeAttribute('aria-hidden');
        li.style.display = '';
    });
    expandLi.setAttribute('hidden', '');
    expandLi.querySelector('button').setAttribute('aria-expanded', 'true');
}

function setupHomeIcon(li) {
    if (!li) return;
    const label = li.querySelector('.c-breadcrumb__label');
    if (!label) return;

    const text = label.textContent.trim();
    label.innerHTML =
        `<i class="fa-solid fa-house c-breadcrumb__home-icon" aria-hidden="true"></i>` +
        `<span class="c-breadcrumb__home-text">${escapeHtml(text)}</span>`;
}

function buildExpandItem(siblingLi) {
    // Clone the separator icon from the adjacent item so it matches the site style
    const separatorClone = siblingLi?.querySelector('.c-icon')?.cloneNode(true) ?? null;

    const li = document.createElement('li');
    li.className = 'c-breadcrumb__item c-breadcrumb__item--expand';
    li.setAttribute('hidden', '');

    if (separatorClone) {
        // Avoid duplicate id / uid attributes in the DOM
        separatorClone.removeAttribute('data-uid');
        separatorClone.removeAttribute('id');
        li.appendChild(separatorClone);
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'c-breadcrumb__expand-btn';
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-label', 'Visa alla steg i sökvägen');
    btn.textContent = '…';
    li.appendChild(btn);

    return li;
}

function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
