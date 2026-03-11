<?php

declare(strict_types=1);

namespace PiteaCustomisation\Helpers;

/**
 * Parses CSS custom properties (--color-*) from an SCSS/CSS file,
 * grouping them by block comments of the form  / * Group Name * /.
 *
 * Returned structure from parseFile():
 *   array<string, list<array{name: string, hex: string, var: string}>>
 *
 *   e.g. ['Blåbära' => [
 *     ['name' => 'Blåbära Dark 3', 'hex' => '#013046', 'var' => '--color-blabara-dark-3'],
 *     ...
 *   ]]
 *
 * Returned structure from toGroupedFlat():
 *   array<string, array<string, string>>   (groupLabel => [colorLabel => hex])
 *
 *   e.g. ['Blåbära' => ['Blåbära Dark 3' => '#013046', ...]]
 */
class ScssColorParser
{
    /**
     * Resolve the path to the variables SCSS file within a plugin directory.
     * Prefers a compiled copy under /data/, falls back to the source tree.
     *
     * @param string $pluginDir Absolute path to the plugin root (no trailing slash)
     * @return string|null Path to the SCSS file, or null if neither location exists
     */
    public static function resolveScssPath(string $pluginDir): ?string
    {
        $candidates = [
            $pluginDir . '/data/variables.scss',
            $pluginDir . '/source/sass/general/variables.scss',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Parse a SCSS/CSS file and return colors grouped by block-comment headings.
     *
     * @param string $scssPath Absolute path to the variables file
     * @return array<string, list<array{name: string, hex: string, var: string}>>
     */
    public static function parseFile(string $scssPath): array
    {
        $colorGroups  = [];
        $currentGroup = null;

        if (!file_exists($scssPath)) {
            return $colorGroups;
        }

        $content = file_get_contents($scssPath);
        if ($content === false) {
            return $colorGroups;
        }

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Group heading: /* Group Name */
            if (preg_match('/^\/\*\s*(.+?)\s*\*\/$/', $line, $m)) {
                $currentGroup = trim($m[1]);
                if (!isset($colorGroups[$currentGroup])) {
                    $colorGroups[$currentGroup] = [];
                }
                continue;
            }

            // Color variable: --color-something: #rrggbb;
            if (!preg_match('/^--color-([^:]+):\s*(#[0-9A-Fa-f]{6});?\s*$/', $line, $m)) {
                continue;
            }

            if ($currentGroup === null) {
                $currentGroup = 'Other';
                if (!isset($colorGroups[$currentGroup])) {
                    $colorGroups[$currentGroup] = [];
                }
            }

            $varName = trim($m[1]);
            $hex     = strtoupper(trim($m[2]));

            // Build a human label: prefer "GroupName Suffix" when the variable
            // starts with the group's ASCII slug (e.g. "Pären" -> slug "paren").
            $colorName = self::buildColorName($varName, $currentGroup);

            $colorGroups[$currentGroup][] = [
                'name' => $colorName,
                'hex'  => $hex,
                'var'  => '--color-' . $varName,
            ];
        }

        return $colorGroups;
    }

    /**
     * Convert the rich grouped structure returned by parseFile() into the
     * simpler grouped-flat format used by the ColorThemeField pickers:
     *   array<groupLabel, array<colorLabel, hex>>
     *
     * @param array<string, list<array{name: string, hex: string, var: string}>> $colorGroups
     * @return array<string, array<string, string>>
     */
    public static function toGroupedFlat(array $colorGroups): array
    {
        $flat = [];
        foreach ($colorGroups as $groupName => $colors) {
            $flat[$groupName] = [];
            foreach ($colors as $color) {
                $flat[$groupName][$color['name']] = $color['hex'];
            }
        }
        return $flat;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Build a human-readable color label.
     * When the variable starts with the group's ASCII slug the group name
     * (with original diacritics) is used as the prefix instead of the slug.
     *
     * e.g. group "Blåbära", var "blabara-dark-3"  → "Blåbära Dark 3"
     *      group "Viola",   var "viola-base"       → "Viola Base"
     *      group "Other",   var "brand-primary"    → "Brand Primary"
     */
    private static function buildColorName(string $varName, string $groupName): string
    {
        $parts     = explode('-', $varName);
        $groupSlug = self::slugifyGroupName($groupName);

        if ($groupSlug !== '' && ($parts[0] ?? '') === $groupSlug) {
            $remainder = array_slice($parts, 1);
            if (!empty($remainder)) {
                return $groupName . ' ' . self::formatVariableName(implode('-', $remainder));
            }
            return $groupName;
        }

        return self::formatVariableName($varName);
    }

    /**
     * Convert a hyphen-separated variable name fragment to Title Case.
     * e.g. "dark-3" → "Dark 3"
     *
     * @param string $varName
     * @return string
     */
    public static function formatVariableName(string $varName): string
    {
        $parts = explode('-', $varName);
        $parts = array_map(static fn($p) => mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'), $parts);
        return implode(' ', $parts);
    }

    /**
     * Create an ASCII slug from a group name for CSS-variable prefix matching.
     * e.g. "Blåbära" → "blabara", "Pären" → "paren", "Dårjnålen" → "darjnalen"
     *
     * @param string $name
     * @return string
     */
    public static function slugifyGroupName(string $name): string
    {
        $s = $name;

        if (function_exists('transliterator_transliterate')) {
            $res = transliterator_transliterate('Any-Latin; Latin-ASCII;', $name);
            if ($res !== false && $res !== null) {
                $s = $res;
            }
        } elseif (function_exists('iconv')) {
            $res = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
            if ($res !== false && $res !== null) {
                $s = $res;
            }
        } else {
            // Fallback: common Scandinavian replacements
            $s = strtr($s, [
                'å' => 'a', 'ä' => 'a', 'ö' => 'o',
                'Å' => 'A', 'Ä' => 'A', 'Ö' => 'O',
                'é' => 'e', 'è' => 'e', 'ü' => 'u', 'ß' => 'ss',
            ]);
        }

        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}
