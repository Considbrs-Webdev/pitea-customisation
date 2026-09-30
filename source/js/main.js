import { initShareButtons } from './modules/share-button.js';
import { initMobileBreadcrumbs } from './modules/breadcrumbs.js';
import { initAccButtonsMobileInsertion } from './modules/acc-buttons.js';
import { initQuickExit } from './modules/quick-exit.js';

function init() {
    initShareButtons();
    initMobileBreadcrumbs();
    initAccButtonsMobileInsertion();
    initQuickExit();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
