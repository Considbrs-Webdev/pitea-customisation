<?php

namespace PiteaCustomisation\Customisations;

class Templates 
{
    public function __construct()
    {
        add_filter('Municipio/viewPaths', [$this, 'registerViewPaths'], 10, 1);
    }

    /**
     * Register the plugin's views directory so Blade can resolve templates.
     *
     * @return string[] Updated array of view paths
     */
    public function registerViewPaths($paths): array
    {
        return array_merge($paths, [PITEA_CUSTOMISATION_PATH . 'views']);
    }
}