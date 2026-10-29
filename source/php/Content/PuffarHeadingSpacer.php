<?php

declare(strict_types=1);

namespace PiteaCustomisation\Content;

/**
 * Removes the spacer block that sits directly between a heading and a puff module.
 *
 * Manual Input card grids carry a 32px top margin since Municipio 7.32.6, and
 * link cards carry a 16px grid margin. The Temasida starter inserted another
 * spacer in those two spots, and pages created from it still have that copy.
 *
 * Only these sequences are removed:
 * - a heading, then a spacer, then Manual Input "Modul: Puffar/inkastare undersida"
 * - a heading, then a spacer, then link cards inside "Modul: Puffar/inkastare med ikon"
 */
class PuffarHeadingSpacer
{
    private const UNDER_PATTERN = 'core/block/1858"';
    private const UNDER_NAME = 'Modul: Puffar/inkastare undersida"';
    private const ICON_PATTERN = 'core/block/1872"';
    private const ICON_NAME = 'Modul: Puffar/inkastare med ikon"';

    /**
     * @return array{content:string,removed:int,details:list<string>}
     */
    public static function strip(string $content): array
    {
        [$content, $removed, $details] = self::rewrite($content, '');

        return [
            'content' => $content,
            'removed' => $removed,
            'details' => $details,
        ];
    }

    /**
     * @return array{0:string,1:int,2:list<string>}
     */
    private static function rewrite(string $html, string $parentAttrs): array
    {
        $original = $html;
        $blocks = self::scan($html);
        if ($blocks === null) {
            return [$original, 0, []];
        }

        $removed = 0;
        $details = [];
        for ($index = count($blocks) - 1; $index >= 0; $index--) {
            $block = $blocks[$index];
            if ($block['innerStart'] === null) {
                continue;
            }

            $innerLength = $block['innerEnd'] - $block['innerStart'];
            $inner = substr($html, $block['innerStart'], $innerLength);
            [$newInner, $innerRemoved, $innerDetails] = self::rewrite($inner, $block['attrs']);
            if ($newInner === $inner) {
                continue;
            }

            $html = substr_replace($html, $newInner, $block['innerStart'], $innerLength);
            $removed += $innerRemoved;
            $details = array_merge($innerDetails, $details);
        }

        $blocks = self::scan($html);
        if ($blocks === null) {
            return [$original, 0, []];
        }

        $ranges = [];
        $count = count($blocks);
        for ($index = 0; $index < $count; $index++) {
            if ($blocks[$index]['name'] !== 'acf/spacer') {
                continue;
            }

            $previous = $blocks[$index - 1]['name'] ?? '';
            $next = $blocks[$index + 1] ?? null;
            if ($previous !== 'heading' || $next === null || !self::isTarget($next, $parentAttrs)) {
                continue;
            }

            $ranges[] = [
                'start' => $blocks[$index]['start'],
                'end' => $blocks[$index]['end'],
                'detail' => self::describe($blocks[$index], $next),
            ];
        }

        for ($index = count($ranges) - 1; $index >= 0; $index--) {
            $range = $ranges[$index];
            $html = substr_replace($html, '', $range['start'], $range['end'] - $range['start']);
            $removed++;
            array_unshift($details, $range['detail']);
        }

        return [$html, $removed, $details];
    }

    /**
     * @param array{name:string,attrs:string} $next
     */
    private static function isTarget(array $next, string $parentAttrs): bool
    {
        if ($next['name'] === 'acf/manualinput') {
            return str_contains($next['attrs'], self::UNDER_PATTERN)
                || str_contains($next['attrs'], self::UNDER_NAME);
        }

        if ($next['name'] === 'acf/link-cards') {
            return str_contains($parentAttrs, self::ICON_PATTERN)
                || str_contains($parentAttrs, self::ICON_NAME);
        }

        return false;
    }

    /**
     * @param array{attrs:string} $spacer
     * @param array{name:string} $next
     */
    private static function describe(array $spacer, array $next): string
    {
        $amount = '?';
        if (preg_match('/"space_amount":"([^"]*)"/', $spacer['attrs'], $match) === 1) {
            $amount = $match[1];
        }

        $target = $next['name'] === 'acf/link-cards' ? 'link-cards' : 'manualinput';

        return $target . ' space ' . $amount;
    }

    /**
     * @return list<array{name:string,start:int,end:int,attrs:string,innerStart:int|null,innerEnd:int|null}>|null
     */
    private static function scan(string $html): ?array
    {
        $blocks = [];
        $offset = 0;
        $length = strlen($html);

        while ($offset < $length) {
            $start = strpos($html, '<!-- wp:', $offset);
            if ($start === false) {
                break;
            }

            $opener = self::parseOpener($html, $start);
            if ($opener === null) {
                return null;
            }

            if ($opener['selfClosing']) {
                $blocks[] = [
                    'name' => $opener['name'],
                    'start' => $start,
                    'end' => $opener['openEnd'],
                    'attrs' => $opener['attrs'],
                    'innerStart' => null,
                    'innerEnd' => null,
                ];
                $offset = $opener['openEnd'];
                continue;
            }

            $closer = self::findCloser($html, $opener['openEnd'], $opener['name']);
            if ($closer === null) {
                return null;
            }

            $blocks[] = [
                'name' => $opener['name'],
                'start' => $start,
                'end' => $closer['end'],
                'attrs' => $opener['attrs'],
                'innerStart' => $opener['openEnd'],
                'innerEnd' => $closer['start'],
            ];
            $offset = $closer['end'];
        }

        return $blocks;
    }

    /**
     * @return array{name:string,attrs:string,selfClosing:bool,openEnd:int}|null
     */
    private static function parseOpener(string $html, int $start): ?array
    {
        $prefix = '<!-- wp:';
        if (substr($html, $start, strlen($prefix)) !== $prefix) {
            return null;
        }

        $namePos = $start + strlen($prefix);
        $matched = preg_match(
            '/\G([a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)?)/',
            $html,
            $nameMatch,
            0,
            $namePos
        );
        if ($matched !== 1) {
            return null;
        }

        $name = $nameMatch[1];
        $cursor = $namePos + strlen($name);
        if (!self::hasNameBoundary($html, $cursor)) {
            return null;
        }

        if (isset($html[$cursor]) && $html[$cursor] === ' ') {
            $cursor++;
        }

        $attrs = '';
        if (isset($html[$cursor]) && $html[$cursor] === '{') {
            $jsonEnd = self::endOfJson($html, $cursor);
            if ($jsonEnd === null) {
                return null;
            }
            $attrs = substr($html, $cursor, $jsonEnd - $cursor);
            $cursor = $jsonEnd;
        }

        while (isset($html[$cursor]) && strpos(" \t\r\n", $html[$cursor]) !== false) {
            $cursor++;
        }

        if (substr($html, $cursor, 4) === '/-->') {
            return [
                'name' => $name,
                'attrs' => $attrs,
                'selfClosing' => true,
                'openEnd' => $cursor + 4,
            ];
        }

        if (substr($html, $cursor, 3) === '-->') {
            return [
                'name' => $name,
                'attrs' => $attrs,
                'selfClosing' => false,
                'openEnd' => $cursor + 3,
            ];
        }

        return null;
    }

    /**
     * @return array{start:int,end:int}|null
     */
    private static function findCloser(string $html, int $from, string $name): ?array
    {
        $depth = 1;
        $pos = $from;
        $length = strlen($html);
        $openPrefix = '<!-- wp:' . $name;
        $closePrefix = '<!-- /wp:' . $name;

        while ($pos < $length && $depth > 0) {
            $nextOpen = strpos($html, $openPrefix, $pos);
            $nextClose = strpos($html, $closePrefix, $pos);
            if ($nextClose === false) {
                return null;
            }

            $openIsCandidate = $nextOpen !== false
                && $nextOpen < $nextClose
                && self::hasNameBoundary($html, $nextOpen + strlen($openPrefix));

            if ($openIsCandidate) {
                $nested = self::parseOpener($html, $nextOpen);
                if ($nested === null || $nested['name'] !== $name) {
                    $pos = $nextOpen + strlen($openPrefix);
                    continue;
                }
                if (!$nested['selfClosing']) {
                    $depth++;
                }
                $pos = $nested['openEnd'];
                continue;
            }

            if (!self::hasNameBoundary($html, $nextClose + strlen($closePrefix))) {
                $pos = $nextClose + strlen($closePrefix);
                continue;
            }

            $afterName = $nextClose + strlen($closePrefix);
            if (preg_match('/\G\s*-->/', $html, $closerMatch, 0, $afterName) !== 1) {
                $pos = $afterName + 1;
                continue;
            }

            $closeEnd = $afterName + strlen($closerMatch[0]);
            $depth--;
            if ($depth === 0) {
                return ['start' => $nextClose, 'end' => $closeEnd];
            }
            $pos = $closeEnd;
        }

        return null;
    }

    private static function hasNameBoundary(string $html, int $position): bool
    {
        if (!isset($html[$position])) {
            return true;
        }

        return preg_match('/[a-z0-9-]/', $html[$position]) !== 1;
    }

    private static function endOfJson(string $html, int $start): ?int
    {
        $length = strlen($html);
        if (!isset($html[$start]) || $html[$start] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        for ($index = $start; $index < $length; $index++) {
            $char = $html[$index];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $index + 1;
                }
            }
        }

        return null;
    }
}
