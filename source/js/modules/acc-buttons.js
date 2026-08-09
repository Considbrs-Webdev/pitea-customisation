/**
 * Moves the mobile clone of the AccButtons module (see acc-buttons.blade.php)
 * to the top of #main-content, so it isn't stuck at the bottom of the page
 * when sidebars stack below the main content on mobile.
 *
 * Inserted inside .o-container (rather than #main-content itself) so the
 * clone picks up the same horizontal padding as the rest of the page content.
 */
export function initAccButtonsMobileInsertion() {
    const container = document.querySelector('#main-content > .o-container');
    if (!container) return;

    document.querySelectorAll('[data-acc-buttons-mobile-root]').forEach((el) => {
        container.prepend(el);
    });
}
