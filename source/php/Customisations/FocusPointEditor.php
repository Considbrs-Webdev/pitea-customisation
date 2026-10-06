<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Helpers\CacheBust;

/**
 * Restores hero Bakgrundsbild focus clicks in the iframed block editor.
 *
 * @upstream-shim id=acf-focuspoint-hero-iframe
 * @upstream-repo acf-focuspoint (+ Gutenberg iframe)
 * @upstream-broken Focus point assets load on editor shell only; hero field in canvas iframe lacks click handler and sizing.
 * @upstream-fix-needed Enqueue focuspoint styles/script for block editor iframe assets.
 * @remove-when acf-focuspoint supports iframe canvas; hero background focus works without this class.
 * @verify-removal Edit hero block; click background image to set focus point in iframe.
 */
class FocusPointEditor
{
    /**
     * Register editor asset hooks.
     */
    public function __construct()
    {
        add_action('enqueue_block_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * Load focuspoint styles and the click bridge in the editor shell and canvas iframe.
     *
     * enqueue_block_assets runs for the parent editor and again while WordPress
     * collects iframe assets. It does not run on the public site.
     *
     * @return void
     */
    public function enqueueEditorAssets(): void
    {
        if (!is_admin()) {
            return;
        }

        $this->enqueueFocusPointStyle();
        $this->enqueueClickBridge();
    }

    /**
     * Ensure the field stylesheet is registered, then enqueue it.
     *
     * The iframe asset collector copies registered styles but only prints
     * handles enqueued during enqueue_block_assets.
     *
     * @return void
     */
    private function enqueueFocusPointStyle(): void
    {
        $relative = '/acf-focuspoint/assets/css/input.min.css';
        $path = WPMU_PLUGIN_DIR . $relative;

        if (!wp_style_is('acffp', 'registered') && is_readable($path)) {
            wp_register_style(
                'acffp',
                WPMU_PLUGIN_URL . $relative,
                [],
                (string) filemtime($path)
            );
        }

        if (wp_style_is('acffp', 'registered')) {
            wp_enqueue_style('acffp');
        }
    }

    /**
     * Enqueue the delegated click handler.
     *
     * @return void
     */
    private function enqueueClickBridge(): void
    {
        $script = CacheBust::getFile('source/js/editor-plugins/acf-focuspoint-editor.js');
        if ($script === '') {
            return;
        }

        wp_enqueue_script(
            'pitea-focuspoint-editor',
            $script,
            [],
            null,
            true
        );
    }
}
