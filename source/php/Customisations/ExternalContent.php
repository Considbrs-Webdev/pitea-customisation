<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\ExternalContent\Search\EServices\EServicesImporter;
use PiteaCustomisation\ExternalContent\ServiceInfo\TrafficDisruptionsImporter;

class ExternalContent
{
    public function __construct()
    {
        $this->registerServiceInfoImporters();
        $this->registerTypesenseStrategies();
    }

    /**
     * Register importers for the modularity-service-info cron.
     *
     * @return void
     */
    private function registerServiceInfoImporters(): void
    {
        add_action('modularity_service_info_register_importers', function ($registry): void {
            $registry->register(new TrafficDisruptionsImporter());
        });
    }

    /**
     * Register external indexing strategies with the Typesense search plugin.
     *
     * Hooks into 'Municipio/TypesenseSearch/RegisterStrategies' which fires
     * after the built-in strategies are registered, allowing third-party plugins
     * to add their own without modifying typesense-search.
     *
     * @return void
     */
    private function registerTypesenseStrategies(): void
    {
        add_action(
            'Municipio/TypesenseSearch/RegisterStrategies',
            function ($registry): void {
                if (!class_exists(EServicesImporter::class)) {
                    return;
                }

                $strategy = new EServicesImporter();
                $registry->registerExternal($strategy);
                $strategy->registerHooks();
            }
        );
    }
}