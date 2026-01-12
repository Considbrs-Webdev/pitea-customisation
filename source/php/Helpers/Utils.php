<?php
namespace PiteaCustomisation\Helpers;

class Utils
{
    /**
     * Parse a string of HTML attributes into an associative array.
     *
     * @param string $input The string containing HTML attributes.
     * @return array An associative array of attribute names and values.
     */
    public static function parseAttributes(string $input): array
    {
        $attrs = [];
        $pattern = '/([^\s=]+)(?:\s*=\s*(?:\'([^\']*)\'|"([^"]*)"|([^\s"\'=<>`]+)))?/u';

        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = $m[1];
                $value = null;

                if (isset($m[2]) && $m[2] !== '') {
                    $value = $m[2];
                } elseif (isset($m[3]) && $m[3] !== '') {
                    $value = $m[3];
                } elseif (isset($m[4]) && $m[4] !== '') {
                    $value = $m[4];
                } else {
                    $value = true; // boolean attribute
                }

                if ($value !== true) {
                    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    /**
     * Check if a given substring exists in attribute values.
     *
     * Accepts either a string of attributes (will be parsed) or an associative array of attributes.
     *
     * @param string|array $attributes Attribute string or associative array.
     * @param string $needle Substring to search for.
     * @param bool $caseInsensitive Whether the search is case-insensitive (default true).
     * @return bool
     */
    public static function containsInAttributes($attributes, string $needle, bool $caseInsensitive = true): bool
    {
        if ($needle === '') {
            return false;
        }

        if (is_string($attributes)) {
            $attributes = self::parseAttributes($attributes);
        } elseif (!is_array($attributes)) {
            return false;
        }

        foreach ($attributes as $value) {
            if ($value === true || $value === null) {
                continue;
            }

            $haystack = (string)$value;
            if ($caseInsensitive) {
                if (stripos($haystack, $needle) !== false) {
                    return true;
                }
            } else {
                if (strpos($haystack, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}