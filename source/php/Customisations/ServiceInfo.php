<?php

/**
 * Service information customisations (modularity-service-info).
 *
 * @package PiteaCustomisation\Customisations
 */

namespace PiteaCustomisation\Customisations;

/**
 * Hooks for service information display and icon overrides.
 */
class ServiceInfo
{
    private const META_SOURCE_KEY = '_service_info_import_source';
    private const PIREVA_SOURCE_PREFIX = 'pireva-disruptions';
    private const PIREVA_DISRUPTIONS_SVG_FILENAME = 'source/assets/svg/pireva-disruptions.svg';

    public function __construct()
    {
        add_filter(
            'ModularityServiceInfo/customIconSvg',
            [$this, 'maybePirevaImportCustomIconSvg'],
            10,
            2
        );
    }

    /**
     * Inject inline SVG for posts imported from Pireva driftstörningar.
     *
     * @param string|null $svg
     * @param int         $postId
     * @return string|null
     */
    public function maybePirevaImportCustomIconSvg(?string $svg, int $postId): ?string
    {
        $source = get_post_meta($postId, self::META_SOURCE_KEY, true);

        if (!is_string($source) || !str_starts_with($source, self::PIREVA_SOURCE_PREFIX)) {
            return $svg;
        }

        $svgPath = PITEA_CUSTOMISATION_PATH . self::PIREVA_DISRUPTIONS_SVG_FILENAME;

        if (!is_readable($svgPath)) {
            return $svg;
        }

        $contents = file_get_contents($svgPath);

        if ($contents === false || $contents === '') {
            return $svg;
        }

        return $contents;
    }
}
