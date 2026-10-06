<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Helpers\CacheBust;

/**
 * Loads the Piteå editor canvas stylesheet inside the iframed block editor.
 *
 * WordPress collects styles enqueued on `enqueue_block_assets` into the editor
 * iframe. Styles on `enqueue_block_editor_assets` stay on the parent editor UI.
 * Module stylesheets are enqueued by each module plugin, not here.
 */
class EditorModuleStyles
{
    /**
     * Register the editor-canvas style hook.
     */
    public function __construct()
    {
        add_action('enqueue_block_assets', [$this, 'enqueue']);
    }

    /**
     * Enqueue the canvas stylesheet (content width and editor rules).
     *
     * @return void
     */
    public function enqueue(): void
    {
        if (!is_admin()) {
            return;
        }

        $canvas = CacheBust::getFile('source/sass/admin.scss');
        if ($canvas === '') {
            return;
        }

        wp_enqueue_style(
            'pitea-customisation-editor-canvas',
            $canvas,
            [],
            PITEA_CUSTOMISATION_VERSION
        );
    }
}
