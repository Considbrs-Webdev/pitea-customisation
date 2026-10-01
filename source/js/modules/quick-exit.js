const SHORTCUT_WINDOW_MS = 5000;
const HEADER_GAP_PX = 16;
const DESKTOP_QUERY = '(min-width: 78em)';

let overlay = null;

/**
 * Blanks the screen straight away, then replaces the current page so Back does not return to it.
 */
function leave(url, leavingMessage) {
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'quick-exit-overlay';
        overlay.setAttribute('role', 'status');
        overlay.textContent = leavingMessage || '';
        document.body.appendChild(overlay);
    }

    window.location.replace(url);
}

function readMessages(panel) {
    try {
        return JSON.parse(panel.getAttribute('data-quick-exit-messages') || '{}');
    } catch (error) {
        return {};
    }
}

function moveStickyToBody() {
    const panels = document.querySelectorAll('[data-quick-exit-sticky]');
    panels.forEach((panel, index) => {
        if (index === 0) {
            document.body.appendChild(panel);
        } else {
            panel.remove();
        }
    });

    return panels.length > 0 ? document.querySelector('body > [data-quick-exit-sticky]') : null;
}

/**
 * Keeps the sticky panel just below the header, which itself sticks once the page scrolls.
 * The value is written to --quick-exit-top; the stylesheet only uses it on desktop.
 */
function anchorUnderHeader(panel) {
    const headers = [...document.querySelectorAll('.c-header--sticky, .site-header')];
    const media = window.matchMedia(DESKTOP_QUERY);
    let frame = 0;

    const update = () => {
        frame = 0;
        if (!media.matches) {
            panel.style.removeProperty('--quick-exit-top');
            return;
        }

        const bottoms = headers
            .map((header) => header.getBoundingClientRect())
            .filter((rect) => rect.height > 0)
            .map((rect) => rect.bottom);
        const anchor = Math.max(0, ...bottoms);
        panel.style.setProperty('--quick-exit-top', `${Math.round(anchor + HEADER_GAP_PX)}px`);
    };

    const schedule = () => {
        if (!frame) {
            frame = window.requestAnimationFrame(update);
        }
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    media.addEventListener('change', schedule);
    update();
    panel.setAttribute('data-quick-exit-ready', '');
}

function announcer() {
    const region = document.createElement('div');
    region.className = 'visually-hidden';
    region.setAttribute('aria-live', 'assertive');
    region.setAttribute('role', 'status');
    document.body.appendChild(region);

    return (message) => {
        region.textContent = '';
        window.setTimeout(() => {
            region.textContent = message;
        }, 50);
    };
}

/**
 * Leaves after Shift is pressed three times on its own, within five seconds, with no other key in between.
 */
function initShiftShortcut(link, messages) {
    const panels = document.querySelectorAll('[data-quick-exit-shortcut]');
    const announce = announcer();
    let presses = 0;
    let shiftIsClean = false;
    let timer = 0;

    const showProgress = () => {
        panels.forEach((panel) => {
            panel.querySelectorAll('.mod-quick-exit__dots i').forEach((dot, index) => {
                dot.classList.toggle('is-filled', index < presses);
            });
            panel.classList.toggle('is-counting', presses > 0);
        });
    };

    const reset = (timedOut) => {
        const wasCounting = presses > 0;
        presses = 0;
        shiftIsClean = false;
        window.clearTimeout(timer);
        showProgress();
        if (timedOut && wasCounting && messages.timedOut) {
            announce(messages.timedOut);
        }
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Shift') {
            if (!event.repeat) {
                shiftIsClean = true;
            }
            return;
        }

        shiftIsClean = false;
        if (presses > 0) {
            reset(false);
        }
    });

    document.addEventListener('keyup', (event) => {
        if (event.key !== 'Shift' || !shiftIsClean) {
            return;
        }

        shiftIsClean = false;
        presses += 1;
        window.clearTimeout(timer);

        if (presses >= 3) {
            announce(messages.leaving || '');
            leave(link.href, messages.leaving);
            return;
        }

        timer = window.setTimeout(() => reset(true), SHORTCUT_WINDOW_MS);
        showProgress();
        announce(presses === 1 ? messages.pressTwo : messages.pressOne);
    });
}

/**
 * Adds a first-in-tab-order link so keyboard and screen-reader users can leave without searching for the panel.
 */
function addSkipLink(link, label) {
    const skip = document.createElement('a');
    skip.href = link.href;
    skip.rel = link.rel;
    skip.className = 'mod-quick-exit__skip visually-hidden-focusable';
    skip.textContent = label;
    skip.setAttribute('data-quick-exit', '');

    const existingSkipLink = document.querySelector('body > a[href^="#"]');
    if (existingSkipLink) {
        existingSkipLink.after(skip);
    } else {
        document.body.prepend(skip);
    }
}

export function initQuickExit() {
    const sticky = moveStickyToBody();
    const firstLink = document.querySelector('[data-quick-exit]');
    if (!firstLink) {
        return;
    }

    const messagePanel = document.querySelector('[data-quick-exit-messages]');
    const messages = messagePanel ? readMessages(messagePanel) : {};

    if (document.querySelector('[data-quick-exit-shortcut]')) {
        initShiftShortcut(firstLink, messages);
    }

    addSkipLink(firstLink, firstLink.textContent.trim());

    document.querySelectorAll('[data-quick-exit]').forEach((element) => {
        if (!(element instanceof HTMLAnchorElement)) {
            return;
        }

        element.addEventListener('click', (event) => {
            event.preventDefault();
            leave(element.href, messages.leaving);
        });
    });

    if (sticky) {
        anchorUnderHeader(sticky);
    }
}
