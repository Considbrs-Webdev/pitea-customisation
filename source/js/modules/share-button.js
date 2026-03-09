/**
 * Share button functionality
 * Copies page URL to clipboard when clicked
 */

function addUtmParameters(url) {
    try {
        const urlObj = new URL(url);

        // Add UTM parameters
        urlObj.searchParams.set('utm_source', 'share');
        urlObj.searchParams.set('utm_medium', 'copy_link');

        return urlObj.toString();
    } catch (e) {
        // Fallback for relative URLs or invalid URLs
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'utm_source=share&utm_medium=copy_link';
    }
}

async function copyToClipboard(text) {
    // Use modern Clipboard API if available
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return;
        } catch (err) {
            // Fall back to legacy method
        }
    }

    // Fallback for older browsers
    return new Promise((resolve, reject) => {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            if (successful) {
                resolve();
            } else {
                reject(new Error('Copy command failed'));
            }
        } catch (err) {
            document.body.removeChild(textArea);
            reject(err);
        }
    });
}

function showCopyPopup(link, success = true) {
    // Announce to screen readers
    const announcer = link.parentElement.querySelector('.share-popup-announcer');
    if (announcer) {
        announcer.textContent = success ? 'Kopierad' : 'Misslyckades';
        // Clear after announcement is read
        setTimeout(() => {
            announcer.textContent = '';
        }, 1000);
    }

    // Remove any existing popup
    const existingPopup = link.parentElement.querySelector('.share-popup');
    if (existingPopup) {
        existingPopup.remove();
    }

    // Create popup element
    const popup = document.createElement('div');
    popup.className = 'share-popup';
    popup.setAttribute('role', 'status');
    popup.setAttribute('aria-live', 'polite');
    popup.textContent = success ? 'Kopierad' : 'Misslyckades';

    // Insert popup before the link
    link.parentElement.insertBefore(popup, link);

    // Position popup above the link (centered)
    // Wait for next frame to ensure popup is rendered and we can measure it
    requestAnimationFrame(() => {
        const linkRect = link.getBoundingClientRect();
        const popupRect = popup.getBoundingClientRect();
        const containerRect = link.parentElement.getBoundingClientRect();

        // Calculate position (centered above the link)
        const leftOffset = linkRect.left - containerRect.left + (linkRect.width / 2) - (popupRect.width / 2);
        popup.style.left = leftOffset + 'px';
        popup.style.bottom = (containerRect.bottom - linkRect.top + 8) + 'px';

        // Show popup with animation after positioning
        requestAnimationFrame(() => {
            popup.classList.add('share-popup--visible');
        });
    });

    // Remove popup after 2 seconds
    setTimeout(() => {
        popup.classList.remove('share-popup--visible');
        setTimeout(() => {
            if (popup.parentElement) {
                popup.remove();
            }
        }, 300); // Wait for fade-out animation
    }, 2000);
}

function handleShareClick(event) {
    event.preventDefault();
    const link = event.currentTarget;
    const baseUrl = link.getAttribute('data-share-url') || window.location.href;
    const urlWithParams = addUtmParameters(baseUrl);

    // Copy to clipboard
    copyToClipboard(urlWithParams).then(() => {
        // Show success feedback popup
        showCopyPopup(link);
    }).catch(() => {
        // Show error feedback popup
        showCopyPopup(link, false);
    });
}

export function initShareButtons() {
    const shareButtons = document.querySelectorAll('[data-js-share-button]');

    if (!shareButtons.length) {
        return;
    }

    shareButtons.forEach(button => {
        button.addEventListener('click', handleShareClick);
    });
}
