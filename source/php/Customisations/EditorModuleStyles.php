<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Helpers\CacheBust;

/**
 * Loads Consid module stylesheets inside the iframed block editor canvas.
 *
 * WordPress collects styles enqueued on `enqueue_block_assets` into the editor
 * iframe (`_wp_get_iframed_editor_assets`). Styles on `enqueue_block_editor_assets`
 * stay on the parent editor UI and do not reach the preview.
 *
 * Modules register styles with the `Pitea/Editor/ModuleStyles` filter:
 * handle (string) => stylesheet URL (string).
 */
class EditorModuleStyles
{
    public const FILTER = 'Pitea/Editor/ModuleStyles';

    /**
     * Register the editor-canvas style hook.
     */
    public function __construct()
    {
        add_action('enqueue_block_assets', [$this, 'enqueue']);
    }

    /**
     * Enqueue module styles registered for the editor canvas.
     *
     * @return void
     */
    public function enqueue(): void
    {
        if (!is_admin()) {
            return;
        }

        $canvas = CacheBust::getFile('source/sass/admin.scss');
        if ($canvas !== '') {
            wp_enqueue_style(
                'pitea-customisation-editor-canvas',
                $canvas,
                [],
                PITEA_CUSTOMISATION_VERSION
            );
        }

        $styles = apply_filters(self::FILTER, []);
        if (!is_array($styles)) {
            return;
        }

        foreach ($styles as $handle => $src) {
            if (!is_string($handle) || $handle === '' || !is_string($src) || $src === '') {
                continue;
            }

            wp_enqueue_style($handle, $src, [], null);
        }
    }
}
