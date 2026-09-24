<?php

namespace PiteaCustomisation\Customisations;

/**
 * Restore custom column % widths after Municipio 7 strips flex-basis.
 *
 * M7's Municipio\Blocks\Columns removes inline flex-basis and maps widths to
 * the 12-column grid. Asymmetric editor widths (e.g. 58% / 39%) only get
 * o-grid-12 and stack full-width. Re-apply flex-basis from block attrs so
 * those layouts match Municipio 6 / baseline behaviour.
 */
class Columns
{
    /**
     * Hook after Municipio\Blocks\Columns (priority 15).
     */
    public function __construct()
    {
        add_filter('render_block', [$this, 'restoreCustomColumnWidths'], 20, 2);
    }

    /**
     * Re-apply flex-basis on rendered o-grid column blocks from width attrs.
     *
     * @param string $content Rendered block HTML.
     * @param array  $block   Block data.
     * @return string
     */
    public function restoreCustomColumnWidths(string $content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'core/columns') {
            return $content;
        }

        $innerBlocks = $block['innerBlocks'] ?? [];
        if ($innerBlocks === []) {
            return $content;
        }

        $widths = [];
        foreach ($innerBlocks as $innerBlock) {
            $width = $innerBlock['attrs']['width'] ?? null;
            $widths[] = is_string($width) && $width !== '' ? $this->normalizeWidth($width) : null;
        }

        if (!array_filter($widths)) {
            return $content;
        }

        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_NOERROR);

        $columnNodes = [];
        foreach ($doc->getElementsByTagName('*') as $element) {
            $class = $element->getAttribute('class');
            if ($class !== '' && strpos($class, 'o-grid-column-block') !== false) {
                $columnNodes[] = $element;
            }
        }

        foreach ($columnNodes as $index => $columnNode) {
            $width = $widths[$index] ?? null;
            if ($width === null) {
                continue;
            }

            $style = $columnNode->getAttribute('style');
            $style = preg_replace('/(^|;)\s*flex-basis\s*:[^;]+;?/i', '$1', $style) ?? $style;
            $style = preg_replace('/\s*;\s*/', '; ', $style) ?? $style;
            $style = trim($style, " ;");
            $style = ($style === '' ? '' : $style . '; ') . 'flex-basis:' . $width;
            $columnNode->setAttribute('style', $style);
        }

        $grid = null;
        foreach ($doc->getElementsByTagName('div') as $div) {
            $class = $div->getAttribute('class');
            if ($class !== '' && preg_match('/(^|\s)o-grid(\s|$)/', $class)) {
                $grid = $div;
                break;
            }
        }

        if (!$grid instanceof \DOMElement) {
            return $content;
        }

        return $doc->saveHTML($grid) ?: $content;
    }

    /**
     * Ensure width values used as flex-basis end with %.
     *
     * @param string $width Block attribute width.
     * @return string|null Normalized width or null if invalid.
     */
    private function normalizeWidth(string $width): ?string
    {
        $width = trim($width);
        if ($width === '') {
            return null;
        }

        if (preg_match('/^[\d.]+%?$/', $width) !== 1) {
            return null;
        }

        return rtrim($width, '%') . '%';
    }
}
