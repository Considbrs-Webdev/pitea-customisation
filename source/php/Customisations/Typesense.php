<?php
namespace PiteaCustomisation\Customisations;

/**
 * Class Typesense
 *
 * Customizes the Typesense search plugin for the Piteå WordPress installation.
 * Adds support for:
 *   - 'lediga-jobb' post type → jobposting hit template
 *   - 'pitea-eservice' external type → eservice hit template with category display
 */
class Typesense
{
    private const ESERVICE_TEMPLATE_KEY = 'eservice';

    public function __construct()
    {
        // Map our custom post/document types to their respective hit templates
        add_filter('Municipio/TypesenseSearch/postTypeToTemplate', [$this, 'addPostTypeTemplateMapping']);

        // Eservice-specific customizations: template registration and placeholder mappings
        add_filter('Municipio/TypesenseSearch/hitTemplates', [$this, 'addEserviceHitTemplate']);
        add_filter('Municipio/TypesenseSearch/hitTemplateView', [$this, 'resolveEserviceHitTemplateView'], 10, 2);
        add_filter('Municipio/TypesenseSearch/placeholderMappings', [$this, 'addEservicePlaceholderMappings']);
    }

    /**
     * Map post/document types to their respective hit template keys.
     *
     * @param array<string, string> $mapping
     * @return array<string, string>
     */
    public function addPostTypeTemplateMapping(array $mapping): array
    {
        $mapping['lediga-jobb']    = 'jobposting';
        $mapping['pitea-eservice'] = self::ESERVICE_TEMPLATE_KEY;

        return $mapping;
    }

    /**
     * Register the eservice hit template key.
     *
     * @param string[] $templates
     * @return string[]
     */
    public function addEserviceHitTemplate(array $templates): array
    {
        $templates[] = self::ESERVICE_TEMPLATE_KEY;
        return $templates;
    }

    /**
     * Resolve the Blade view path for the eservice hit template.
     *
     * @param string $view Current resolved view path.
     * @param string $key  Template key being resolved.
     * @return string
     */
    public function resolveEserviceHitTemplateView(string $view, string $key): string
    {
        if ($key === self::ESERVICE_TEMPLATE_KEY) {
            return 'search.hits.hit-eservice';
        }
        return $view;
    }

    /**
     * Map custom placeholder keys to Typesense document field paths.
     *
     * SEARCH_HIT_ESERVICE_CATEGORY maps to the first element of the 'tags' array,
     * which holds the e-service category sourced from the eNämnd API.
     *
     * @param array<string, string> $mappings
     * @return array<string, string>
     */
    public function addEservicePlaceholderMappings(array $mappings): array
    {
        return array_merge($mappings, [
            'SEARCH_HIT_ESERVICE_CATEGORY' => 'tags.0',
        ]);
    }
}