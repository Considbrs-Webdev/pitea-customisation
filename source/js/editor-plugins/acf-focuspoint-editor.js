/**
 * Click-to-set focus for the ACF focuspoint field.
 *
 * The field script binds handlers on the editor shell. In edit mode the
 * Bakgrundsbild control is rendered inside the block canvas iframe, so
 * those handlers never see the click. This listener is delegated and
 * copies the new percentages onto the shell inputs that ACF serializes.
 */
(function () {
    var boundFlag = 'data-pitea-focuspoint-bound';

    function roundPercent(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function clampPercent(value) {
        return Math.min(100, Math.max(0, value));
    }

    function notify(input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function writeFocus(root, top, left) {
        var topInput = root.querySelector('[data-name="acf-focuspoint-top"]');
        var leftInput = root.querySelector('[data-name="acf-focuspoint-left"]');
        var picker = root.querySelector('.focal-point-picker');

        if (!topInput || !leftInput) {
            return null;
        }

        topInput.value = String(top);
        leftInput.value = String(left);

        if (picker) {
            picker.style.top = top + '%';
            picker.style.left = left + '%';
        }

        notify(topInput);
        notify(leftInput);

        return { topInput: topInput, leftInput: leftInput };
    }

    function findNamed(doc, input) {
        var nodes = doc.querySelectorAll('[data-name="' + input.getAttribute('data-name') + '"]');
        var index;

        for (index = 0; index < nodes.length; index += 1) {
            if (nodes[index].name === input.name) {
                return nodes[index];
            }
        }

        return null;
    }

    function mirrorToShell(written) {
        var view = written.topInput.ownerDocument.defaultView;
        var shellDoc;

        if (!view || !view.parent || view.parent === view) {
            return;
        }

        try {
            shellDoc = view.parent.document;
        } catch (error) {
            return;
        }

        if (!shellDoc || shellDoc === written.topInput.ownerDocument) {
            return;
        }

        var shellTop = findNamed(shellDoc, written.topInput);
        var shellRoot = shellTop && shellTop.closest('.acf-focuspoint');

        if (!shellRoot) {
            return;
        }

        writeFocus(shellRoot, written.topInput.value, written.leftInput.value);
    }

    function onFocusClick(event) {
        var target = event.target;
        var layer = target && target.closest ? target.closest('.focuspoint-selection-layer') : null;
        var rect;
        var root;
        var written;
        var left;
        var top;

        if (!layer) {
            return;
        }

        rect = layer.getBoundingClientRect();
        if (!rect.width || !rect.height) {
            return;
        }

        left = clampPercent(roundPercent(((event.clientX - rect.left) / rect.width) * 100));
        top = clampPercent(roundPercent(((event.clientY - rect.top) / rect.height) * 100));
        root = layer.closest('.acf-focuspoint');

        if (!root) {
            return;
        }

        written = writeFocus(root, top, left);
        if (written) {
            mirrorToShell(written);
        }
    }

    function bind(doc) {
        if (!doc || !doc.documentElement || doc.documentElement.hasAttribute(boundFlag)) {
            return;
        }

        doc.documentElement.setAttribute(boundFlag, '1');
        doc.addEventListener('click', onFocusClick, true);
    }

    function watchCanvas() {
        var frame = document.querySelector('iframe[name="editor-canvas"]');

        if (!frame || frame.__piteaFocusWatch) {
            return;
        }

        frame.__piteaFocusWatch = true;
        frame.addEventListener('load', function () {
            try {
                bind(frame.contentDocument);
            } catch (error) {
                return;
            }
        });

        try {
            bind(frame.contentDocument);
        } catch (error) {
            return;
        }
    }

    bind(document);

    if (!window.parent || window.parent === window) {
        watchCanvas();

        if (document.body) {
            new MutationObserver(watchCanvas).observe(document.body, {
                childList: true,
                subtree: true,
            });
        }

        if (window.acf && typeof window.acf.addAction === 'function') {
            window.acf.addAction('ready', watchCanvas);
            window.acf.addAction('append', watchCanvas);
        }
    }
})();
