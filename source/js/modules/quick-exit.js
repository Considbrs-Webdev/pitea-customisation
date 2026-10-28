const SHORTCUT_WINDOW_MS = 5000;
const HEADER_GAP_PX = 16;

let overlay = null;

/**
 * Blanks the screen, opens the destination in a new tab, and replaces this tab with the neutral page.
 * A blocked popup replaces this tab with the destination instead.
 *
 * opener is cleared on the new tab. The noopener window feature is not used: Chrome returns
 * null from that call both when the tab opens and when a popup blocker stops it.
 *
 * @param {string} dest
 * @param {string} neutralUrl
 * @param {string} leavingMessage
 */
function leave(dest, neutralUrl, leavingMessage) {
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'quick-exit-overlay';
        overlay.setAttribute('role', 'status');
        overlay.textContent = leavingMessage || '';
        document.body.appendChild(overlay);
    }

    const popup = window.open(dest, '_blank');
    if (!popup) {
        window.location.replace(dest);
        return;
    }

    try {
        popup.opener = null;
    } catch (error) {
        // Cross-origin tabs can refuse the assignment after navigation starts.
    }

    window.location.replace(neutralUrl || dest);
}

/**
 * @param {HTMLAnchorElement} element
 * @returns {string}
 */
function neutralUrlFor(element) {
    const neutral = element.getAttribute('data-quick-exit-neutral');
    return neutral && neutral.trim() !== '' ? neutral : element.href;
}

/**
 * True when url only changes the hash on the current page.
 *
 * @param {string|URL|null|undefined} url
 * @returns {boolean}
 */
function isSamePageHash(url) {
    if (url == null || url === '') {
        return false;
    }

    let next;
    try {
        next = new URL(String(url), window.location.href);
    } catch (error) {
        return false;
    }

    return next.origin === window.location.origin
        && next.pathname === window.location.pathname
        && next.search === window.location.search
        && next.hash.length > 1;
}

/**
 * True when the element can take focus without adding tabindex.
 *
 * @param {HTMLElement} element
 * @returns {boolean}
 */
function canFocusWithoutTabIndex(element) {
    if (element.hasAttribute('tabindex')) {
        return true;
    }

    const tag = element.tagName;
    if (tag === 'A') {
        return element.hasAttribute('href');
    }

    if (tag === 'BUTTON' || tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA' || tag === 'SUMMARY') {
        return !element.disabled;
    }

    return element.isContentEditable;
}

/**
 * Scrolls to a same-page hash target and moves focus there.
 *
 * @param {string} hash
 */
function scrollToHash(hash) {
    let id = hash.slice(1);
    try {
        id = decodeURIComponent(id);
    } catch (error) {
        return;
    }

    const target = document.getElementById(id);
    if (!target) {
        return;
    }

    if (!canFocusWithoutTabIndex(target)) {
        target.setAttribute('tabindex', '-1');
    }

    target.scrollIntoView();
    target.focus({ preventScroll: true });
}

/**
 * Keeps same-page hash navigation off the history stack while a quick-exit module is on the page.
 * Other history updates are left alone.
 */
function keepSamePageHashesOffTheStack() {
    const pushState = History.prototype.pushState;
    History.prototype.pushState = function pushStateWithoutSamePageHash(state, title, url) {
        if (isSamePageHash(url)) {
            return History.prototype.replaceState.call(this, state, title, url);
        }

        return pushState.call(this, state, title, url);
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!(anchor instanceof HTMLAnchorElement) || anchor.hasAttribute('download')) {
            return;
        }

        if (anchor.target && anchor.target !== '_self' && anchor.target !== '_top') {
            return;
        }

        if (!isSamePageHash(anchor.href)) {
            return;
        }

        const url = new URL(anchor.href);
        event.preventDefault();
        const next = `${url.pathname}${url.search}${url.hash}`;
        const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;
        if (next !== current) {
            history.replaceState(history.state, '', next);
        }
        scrollToHash(url.hash);
    });
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
 * The value is written to --quick-exit-top on every viewport.
 */
function anchorUnderHeader(panel) {
    const headers = [...document.querySelectorAll('.c-header--sticky, .site-header')];
    let frame = 0;

    const update = () => {
        frame = 0;

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
            leave(link.href, neutralUrlFor(link), messages.leaving);
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
    const neutral = link.getAttribute('data-quick-exit-neutral');
    if (neutral) {
        skip.setAttribute('data-quick-exit-neutral', neutral);
    }

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
    keepSamePageHashesOffTheStack();

    document.querySelectorAll('[data-quick-exit]').forEach((element) => {
        if (!(element instanceof HTMLAnchorElement)) {
            return;
        }

        element.addEventListener('click', (event) => {
            event.preventDefault();
            leave(element.href, neutralUrlFor(element), messages.leaving);
        });
    });

    if (sticky) {
        anchorUnderHeader(sticky);
    }
}
