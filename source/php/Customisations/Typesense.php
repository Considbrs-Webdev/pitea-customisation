<?php
namespace PiteaCustomisation\Customisations;

/**
 * Class Typesense
 *
 * Customizes the Typesense search plugin for the Piteå WordPress installation.
 * Adds support for a custom post type "lediga-jobb" with a specific search hit template.
 */
class Typesense
{
    public function __construct()
    {
        add_filter('Municipio/TypesenseSearch/postTypeToTemplate', [$this, 'addJobPostingTemplate']);

        add_filter('Municipio/TypesenseSearch/placeholderMappings', function ($mappings) {
            $mappings['SEARCH_HIT_VALID_THROUGH'] = 'last_application_date';
            return $mappings;
        });
    }

    public function addJobPostingTemplate($mapping)
    {
        $mapping['lediga-jobb'] = 'jobposting';

        return $mapping;
    }
}