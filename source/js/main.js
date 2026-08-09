import { initShareButtons } from './modules/share-button.js';
import { initMobileBreadcrumbs } from './modules/breadcrumbs.js';
import { initAccButtonsMobileInsertion } from './modules/acc-buttons.js';

function init() {
    initShareButtons();
    initMobileBreadcrumbs();
    initAccButtonsMobileInsertion();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
