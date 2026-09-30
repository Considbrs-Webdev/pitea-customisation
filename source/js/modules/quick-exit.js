/**
 * Enhances the quick-exit module: one sticky button on the body, location.replace
 * on click, and an optional triple-Escape shortcut.
 */
export function initQuickExit() {
    const sticky = document.querySelectorAll('[data-quick-exit-sticky]');
    if (sticky.length > 0) {
        document.body.appendChild(sticky[0]);
        sticky.forEach((element, index) => {
            if (index > 0) {
                element.remove();
            }
        });
    }

    document.querySelectorAll('[data-quick-exit]').forEach((element) => {
        if (!(element instanceof HTMLAnchorElement) || !element.href) {
            return;
        }

        element.addEventListener('click', (event) => {
            event.preventDefault();
            window.location.replace(element.href);
        });
    });

    const escapeLink = document.querySelector('[data-quick-exit][data-quick-exit-escape="1"]');
    if (!(escapeLink instanceof HTMLAnchorElement) || !escapeLink.href) {
        return;
    }

    let presses = 0;
    let resetTimer = 0;

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || event.repeat) {
            return;
        }

        if (document.querySelector('dialog[open], [aria-modal="true"]')) {
            presses = 0;
            return;
        }

        presses += 1;
        window.clearTimeout(resetTimer);
        resetTimer = window.setTimeout(() => {
            presses = 0;
        }, 1000);

        if (presses >= 3) {
            presses = 0;
            window.location.replace(escapeLink.href);
        }
    });
}
