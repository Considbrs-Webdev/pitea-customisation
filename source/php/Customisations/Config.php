<?php

namespace PiteaCustomisation\Customisations;

class Config
{
    /**
     * Initialize configuration and setup
     */
    public function __construct()
    {
        add_action('acfe/init', [$this, 'configureAcfExtended']);
    }

    /**
     * Configure ACF Extended settings
     *
     * @return void
     */
    public function configureAcfExtended(): void
    {
        // Disable Enhanced UI
        acfe_update_setting('modules/ui', false);
    }
}
