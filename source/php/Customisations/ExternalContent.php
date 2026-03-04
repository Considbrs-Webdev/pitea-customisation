<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\ExternalContent\TrafficDisruptionsImporter;
use PiteaCustomisation\ExternalContent\PirevaDisruptionsImporter;

class ExternalContent
{
    public function __construct()
    {
        $this->registerExternalContentImporters();
    }

    /**
     * Register external content importers for the service information import cron.
     *
     * @return void
     */
    private function registerExternalContentImporters(): void
    {
        add_action('modularity_service_info_register_importers', function ($registry) {
            $registry->register(new TrafficDisruptionsImporter());
            $registry->register(new PirevaDisruptionsImporter());
        });
    }
}
