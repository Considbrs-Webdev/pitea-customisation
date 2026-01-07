<?php

namespace PiteaCustomisation\Customisations;

class Config
{
    /**
     * Initialize configuration and setup
     */
    public function __construct()
    {
        // Load plugin textdomain for translations
        add_action('init', [$this, 'loadTextdomain']);

        // Turn off ACF Extended Enhanced UI
        add_action('acfe/init', [$this, 'configureAcfExtended']);

        // Specifiy Font Awesome archive link icon for service information
        add_filter('Modularity/ServiceInformation/Module/ArchiveLink/Icon', [$this, 'setServiceInfoArchiveLinkIcon']);
    }

    /**
     * Load plugin textdomain for translations
     *
     * @return void
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'pitea-customisation',
            false,
            dirname(dirname(dirname(__DIR__))) . '/languages'
        );
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

    public function setServiceInfoArchiveLinkIcon(): string
    {
        return 'fa-solid fa-arrow-right';
    }
}
