<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\AcfFields\QuickExitFields;
use PiteaCustomisation\Admin\Tabs\QuickExitTab;

/**
 * Boots the quick-exit module fields and the block-editor link to site settings.
 */
class QuickExit
{
    public function __construct()
    {
        new QuickExitFields();
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets'], 20);
    }

    /**
     * Pass the settings-page URL to the block sidebar when the Gutenberg bundle is loaded.
     */
    public function enqueueEditorAssets(): void
    {
        if (!wp_script_is('pitea-gutenberg-fontawesome', 'enqueued')) {
            return;
        }

        wp_localize_script(
            'pitea-gutenberg-fontawesome',
            'piteaQuickExitEditor',
            [
                'settingsUrl' => QuickExitTab::settingsUrl(),
                'panelTitle'  => __('Quick exit', 'pitea-customisation'),
                'linkText'    => __('Edit button text and destination', 'pitea-customisation'),
                'help'        => __('Button text, destination and the explanation apply to the whole site. This module only chooses where the button is shown.', 'pitea-customisation'),
            ]
        );
    }
}
