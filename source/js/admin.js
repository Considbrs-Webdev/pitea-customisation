/**
 * Admin JS entry point.
 *
 * Currently handles the Piteå Customisation settings page:
 * – AJAX save on button click
 * – Toast notification feedback
 * – Tab navigation (JS-driven, no page reload)
 */

// =============================================================================
// Settings page
// =============================================================================

(function initSettingsPage() {
    const root = document.querySelector('.pitea-settings');
    if (!root || typeof window.piteaSettings === 'undefined') return;

    const config     = window.piteaSettings;
    const saveBtn    = root.querySelector('.pitea-settings__save-btn');
    const saveStatus = root.querySelector('.pitea-settings__save-status');
    const navItems   = root.querySelectorAll('.pitea-settings__nav-item');
    const tabs       = root.querySelectorAll('.pitea-settings__tab');

    // -------------------------------------------------------------------------
    // Tab switching (without page reload)
    // -------------------------------------------------------------------------
    navItems.forEach((navItem) => {
        navItem.addEventListener('click', (e) => {
            const tabId = navItem.dataset.tab;

            // Update URL without reload
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            history.replaceState(null, '', url.toString());

            // Activate nav item
            navItems.forEach((n) => n.classList.remove('is-active'));
            navItem.classList.add('is-active');

            // Show the matching tab panel
            tabs.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.tab === tabId);
            });

            root.dataset.activeTab = tabId;
            e.preventDefault();
        });
    });

    // -------------------------------------------------------------------------
    // Save via AJAX
    // -------------------------------------------------------------------------

    /** @returns {HTMLElement|null} */
    function getActiveTab() {
        return root.querySelector('.pitea-settings__tab.is-active');
    }

    /**
     * Collect all named field values from the active tab into a FormData.
     *
     * @returns {FormData}
     */
    function collectFormData() {
        const activeTab  = getActiveTab();
        const formData   = new FormData();

        formData.append('action', config.action);
        formData.append('nonce',  config.nonce);
        formData.append('tab',    root.dataset.activeTab ?? '');

        if (!activeTab) return formData;

        activeTab.querySelectorAll('input, select, textarea').forEach((field) => {
            const name = field.name;
            if (!name) return;

            if (field.type === 'checkbox' || field.type === 'radio') {
                if (field.checked) formData.append(name, field.value);
            } else {
                formData.set(name, field.value);
            }
        });

        return formData;
    }

    /** Show the inline save-status text inside the header. */
    function setStatus(state, text) {
        if (!saveStatus) return;
        saveStatus.className = 'pitea-settings__save-status';
        if (state) saveStatus.classList.add(state);
        saveStatus.textContent = text;
    }

    /** Create + show a toast notification. Auto-hides after 3 s. */
    function showToast(message, type = 'is-success') {
        let toast = root.querySelector('.pitea-settings__toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'pitea-settings__toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.className = `pitea-settings__toast ${type}`;

        // Trigger reflow so the transition fires
        void toast.offsetWidth;
        toast.classList.add('is-visible');

        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 3000);
    }

    // -------------------------------------------------------------------------
    // Page-permissions repeater
    // -------------------------------------------------------------------------
    (function initRepeaters() {
        root.querySelectorAll('[data-repeater]').forEach((repeater) => {
            const addBtn       = repeater.querySelector('.pitea-settings__repeater-add');
            const rowsContainer = repeater.querySelector('.pitea-settings__repeater-rows');
            const template     = repeater.querySelector('template');

            if (!addBtn || !rowsContainer || !template) return;

            function getNextIndex() {
                return rowsContainer.querySelectorAll('.pitea-settings__repeater-row').length;
            }

            function bindRemoveBtn(row) {
                const removeBtn = row.querySelector('.pitea-settings__repeater-remove');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => row.remove());
                }
            }

            // Bind existing rows.
            rowsContainer.querySelectorAll('.pitea-settings__repeater-row').forEach(bindRemoveBtn);

            addBtn.addEventListener('click', () => {
                const index = getNextIndex();
                const html  = template.innerHTML.replaceAll('{{INDEX}}', String(index));
                const temp  = document.createElement('div');
                temp.innerHTML = html;
                const newRow = temp.firstElementChild;
                if (newRow) {
                    rowsContainer.appendChild(newRow);
                    bindRemoveBtn(newRow);
                }
            });
        });
    }());

    if (!saveBtn) return;

    const saveBtnOriginalLabel = saveBtn.textContent;

    saveBtn.addEventListener('click', async () => {
        if (saveBtn.classList.contains('is-loading')) return;

        // --- Loading state ---
        saveBtn.classList.add('is-loading');
        saveBtn.textContent = config.i18n.saving;
        setStatus('is-saving', config.i18n.saving);

        try {
            const formData = collectFormData();
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });

            const json = await response.json();

            if (json.success) {
                setStatus('is-saved', config.i18n.saved);
                showToast(config.i18n.saved, 'is-success');
            } else {
                const msg = json.data?.message ?? config.i18n.error;
                setStatus('is-error', msg);
                showToast(msg, 'is-error');
            }
        } catch {
            setStatus('is-error', config.i18n.error);
            showToast(config.i18n.error, 'is-error');
        } finally {
            saveBtn.classList.remove('is-loading');
            saveBtn.textContent = saveBtnOriginalLabel;

            // Fade out the header status text after a moment
            setTimeout(() => {
                if (saveStatus) saveStatus.classList.remove('is-saved', 'is-error', 'is-saving');
            }, 4000);
        }
    });
}());
