/**
 * Refresh Posts taxonomy selects inside the ACF expanded editor.
 *
 * PHP renders the choices into the block form. This script refills a modal
 * form that was still empty, and updates terms when the post type or
 * taxonomy changes on those new select nodes.
 */
(function () {
    var TAX_KEY = 'field_571e048136f10';
    var TERM_KEY = 'field_571e049636f11';
    var POST_TYPE_KEY = 'field_571dfc40f8114';
    var MODAL = '.acf-block-form-modal';

    function editorPostId() {
        try {
            var id = wp.data.select('core/editor').getCurrentPostId();
            if (id) {
                return String(id);
            }
        } catch (error) {
            return '0';
        }

        if (window.modPostsTaxonomyFiltering && modPostsTaxonomyFiltering.currentPostID) {
            return String(modPostsTaxonomyFiltering.currentPostID);
        }

        return '0';
    }

    function fieldSelect(root, key) {
        return root.querySelector('[data-key="' + key + '"] select');
    }

    function savedValue(select, name, key) {
        var match = (select.id || '').match(/^acf-block_(.+)-field_/);
        if (!match) {
            return '';
        }

        try {
            var block = wp.data.select('core/block-editor').getBlock(match[1]);
            var data = block && block.attributes && block.attributes.data;
            if (!data) {
                return '';
            }
            return data[name] || data[key] || '';
        } catch (error) {
            return '';
        }
    }

    function request(params) {
        if (!window.ajaxurl) {
            return Promise.resolve(null);
        }

        return fetch(window.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(params),
        }).then(function (response) {
            return response.text();
        }).then(function (text) {
            try {
                return JSON.parse(text);
            } catch (error) {
                return null;
            }
        }).catch(function () {
            return null;
        });
    }

    function asList(value) {
        if (!value) {
            return [];
        }
        if (Array.isArray(value)) {
            return value;
        }
        return Object.keys(value).map(function (key) {
            return value[key];
        });
    }

    function taxonomyItems(types) {
        return asList(types).filter(function (tax) {
            return tax && tax.name;
        }).map(function (tax) {
            return { value: String(tax.name), label: String(tax.label || tax.name) };
        });
    }

    function termItems(tax) {
        return asList(tax).filter(function (term) {
            return term && term.slug;
        }).map(function (term) {
            return { value: String(term.slug), label: String(term.name || term.slug) };
        });
    }

    function fillSelect(select, items, selected, commit) {
        if (!select || !items.length) {
            return false;
        }

        var value = selected || select.value || items[0].value;
        select.dataset.piteaFilling = '1';
        select.replaceChildren();

        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            if (item.value === value) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        if (value) {
            select.value = value;
        }

        if (commit) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        delete select.dataset.piteaFilling;
        return true;
    }

    function refreshTerms(modal, commit) {
        var taxSelect = fieldSelect(modal, TAX_KEY);
        var termSelect = fieldSelect(modal, TERM_KEY);
        if (!taxSelect || !termSelect || !taxSelect.value) {
            return Promise.resolve();
        }

        var selected = termSelect.value || savedValue(termSelect, 'posts_taxonomy_value', TERM_KEY);

        return request({
            action: 'get_taxonomy_values_v2',
            tax: taxSelect.value,
            selected: selected,
            post: editorPostId(),
        }).then(function (response) {
            if (!response) {
                return;
            }
            fillSelect(termSelect, termItems(response.tax), selected, commit);
        });
    }

    function refreshFromPostType(modal, commit) {
        var postTypeSelect = fieldSelect(modal, POST_TYPE_KEY);
        var taxSelect = fieldSelect(modal, TAX_KEY);
        if (!postTypeSelect || !taxSelect || !postTypeSelect.value) {
            return Promise.resolve();
        }

        var selectedTax = taxSelect.value || savedValue(taxSelect, 'posts_taxonomy_type', TAX_KEY);

        return request({
            action: 'get_taxonomy_types_v2',
            posttype: postTypeSelect.value,
            selected: selectedTax,
            post: editorPostId(),
        }).then(function (response) {
            if (!response) {
                return;
            }
            fillSelect(taxSelect, taxonomyItems(response.types), selectedTax, commit);
            return refreshTerms(modal, commit);
        });
    }

    function populateEmpty(modal) {
        var taxSelect = fieldSelect(modal, TAX_KEY);
        var termSelect = fieldSelect(modal, TERM_KEY);
        if (!taxSelect || !termSelect) {
            return;
        }
        if (taxSelect.options.length > 0 && termSelect.options.length > 0) {
            return;
        }
        if (modal.dataset.piteaTaxLoading === '1') {
            return;
        }

        modal.dataset.piteaTaxLoading = '1';
        refreshFromPostType(modal, false).finally(function () {
            delete modal.dataset.piteaTaxLoading;
        });
    }

    function scan() {
        document.querySelectorAll(MODAL).forEach(populateEmpty);
    }

    var timer;
    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(scan, 50);
    }

    document.addEventListener('change', function (event) {
        var select = event.target;
        if (!(select instanceof HTMLSelectElement) || select.dataset.piteaFilling === '1') {
            return;
        }

        var modal = select.closest(MODAL);
        if (!modal) {
            return;
        }

        if (select.closest('[data-key="' + POST_TYPE_KEY + '"]')) {
            refreshFromPostType(modal, true);
            return;
        }

        if (select.closest('[data-key="' + TAX_KEY + '"]')) {
            refreshTerms(modal, true);
        }
    });

    var observer = new MutationObserver(schedule);
    function start() {
        observer.observe(document.body, { childList: true, subtree: true });
        scan();
    }

    if (document.body) {
        start();
    } else {
        document.addEventListener('DOMContentLoaded', start);
    }
})();
