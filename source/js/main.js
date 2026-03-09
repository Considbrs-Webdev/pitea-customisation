import { initShareButtons } from './modules/share-button.js';
import { initMobileBreadcrumbs } from './modules/breadcrumbs.js';

function init() {
    initShareButtons();
    initMobileBreadcrumbs();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
