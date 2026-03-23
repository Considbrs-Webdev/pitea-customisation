<?php

declare(strict_types=1);

namespace PiteaCustomisation\Helpers;

/**
 * Loads design-system palette groups (same source as {@see ColorPickerField}).
 */
final class DesignSystemColors
{
    /**
     * @var array<string, list<array{name: string, hex: string, var: string|null}>>|null
     */
    private static ?array $cachedGroups = null;

    /**
     * @return array<string, list<array{name: string, hex: string, var: string|null}>>
     */
    public static function getGroupedColors(): array
    {
        if (self::$cachedGroups !== null) {
            return self::$cachedGroups;
        }

        $colorGroups = [];

        $pluginDir = dirname(__DIR__, 3);
        $scssPath  = ScssColorParser::resolveScssPath($pluginDir);

        if ($scssPath !== null) {
            $colorGroups = ScssColorParser::parseFile($scssPath);
        }

        if ($colorGroups === []) {
            $colorGroups = self::getFallbackColors();
        }

        self::$cachedGroups = apply_filters('PiteaCustomisation/ColorPicker/DesignSystemColorGroups', $colorGroups);

        return self::$cachedGroups;
    }

    /**
     * @return array<string, list<array{name: string, hex: string, var: string|null}>>
     */
    private static function getFallbackColors(): array
    {
        $colorGroups = [];

        if (class_exists(\Municipio\Helper\Color::class)) {
            $palettes = \Municipio\Helper\Color::getPalettes([
                'color_palette_primary',
                'color_palette_secondary',
                'color_palette_complement',
                'color_palette_additional',
            ]);

            foreach ($palettes as $paletteName => $palette) {
                if (!is_array($palette)) {
                    continue;
                }

                $groupName = str_replace('color_palette_', '', $paletteName);
                $groupName = ucfirst((string) $groupName);

                if (!isset($colorGroups[$groupName])) {
                    $colorGroups[$groupName] = [];
                }

                foreach ($palette as $colorKey => $hex) {
                    if (empty($hex)) {
                        continue;
                    }

                    $colorName = ucfirst(str_replace('_', ' ', (string) $colorKey));

                    $colorGroups[$groupName][] = [
                        'name' => $colorName,
                        'hex'  => $hex,
                        'var'  => null,
                    ];
                }
            }
        }

        if ($colorGroups === [] && class_exists(\Municipio\Helper\KirkiSwatches::class)) {
            $swatches = \Municipio\Helper\KirkiSwatches::getColors();
            $colorGroups['Default'] = [];
            foreach ($swatches as $index => $hex) {
                $colorGroups['Default'][] = [
                    'name' => 'Color ' . ((int) $index + 1),
                    'hex'  => $hex,
                    'var'  => null,
                ];
            }
        }

        return $colorGroups;
    }
}
