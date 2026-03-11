<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations\Modules;

use PiteaCustomisation\Helpers\ScssColorParser;

/**
 * Customises the modularity-link-cards module by wiring up the design-system
 * colour palette from variables.scss into the ColorThemeField filters.
 *
 * Filters provided
 * ----------------
 * Modularity/Module/LinkCards/BackgroundColors   — grouped colours for the bg picker
 * Modularity/Module/LinkCards/IconColors         — grouped colours for the icon picker
 */
class LinkCards
{
    public function __construct()
    {
        add_filter('Modularity/Module/LinkCards/BackgroundColors', [$this, 'addDesignSystemColors']);
        add_filter('Modularity/Module/LinkCards/IconColors',       [$this, 'addDesignSystemColors']);
    }

    /**
     * Populate both colour pickers with the full design-system palette,
     * grouped by the block-comment headings in variables.scss.
     *
     * @param array $colors Existing colours already in the filter chain
     * @return array<string, array<string, string>>  groupLabel => [colorLabel => hex]
     */
    public function addDesignSystemColors(array $colors): array
    {
        $pluginDir = dirname(__DIR__, 4); // source/php/Customisations/Modules -> plugin root
        $scssPath  = ScssColorParser::resolveScssPath($pluginDir);

        if ($scssPath === null) {
            return $colors;
        }

        $parsed  = ScssColorParser::parseFile($scssPath);
        $grouped = ScssColorParser::toGroupedFlat($parsed);

        // Design-system groups first, then any colours already in $colors
        return array_merge($grouped, $colors);
    }
}
