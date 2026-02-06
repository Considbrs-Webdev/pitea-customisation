<?php
/**
 * Generate FontAwesome icons list from icons.json
 *
 * This script reads the FontAwesome icons.json file and generates
 * a JSON file where each icon key maps to an object with:
 *   label: human readable label
 *   search: array of search terms
 *   classname: array of FontAwesome classname combinations
 *
 * Usage: php generate-icons.php /path/to/icons.json
 */

$iconsJsonPath = $argv[1] ?? 'icons.json';
$outputPath = dirname(__DIR__) . '/data/fontawesome-icons.json';

// Read and decode the icons JSON file
$iconsData = json_decode(file_get_contents($iconsJsonPath), true);

if (!$iconsData) {
    die("Error: Could not read or parse icons.json\n");
}

$output = [];

// Helper to normalize words
function normalize_terms(array $terms): array {
    $out = [];
    foreach ($terms as $t) {
        $t = trim((string)$t);
        if ($t === '') continue;
        $out[] = strtolower($t);
    }
    return array_values(array_unique($out));
}

// Process each icon
foreach ($iconsData as $iconKey => $iconData) {
    // Skip if not an array (handles the numeric keys at the start)
    if (!is_array($iconData)) {
        continue;
    }

    $label = $iconData['label'] ?? ucfirst(str_replace('-', ' ', $iconKey));

    // Determine styles (try canonical 'styles' first, fall back to 'free')
    $styles = [];
    if (isset($iconData['styles']) && is_array($iconData['styles'])) {
        $styles = $iconData['styles'];
    } elseif (isset($iconData['free']) && is_array($iconData['free'])) {
        $styles = $iconData['free'];
    }

    // Build classname combinations for each style
    $classnames = [];
    foreach ($styles as $style) {
        $style = trim($style);
        if ($style === '') continue;
        $classnames[] = "fa-{$style} fa-{$iconKey}";
    }

    // Build search terms: include any search terms provided by icons.json,
    // plus words from the label and parts of the icon key
    $searchTerms = [];
    if (isset($iconData['search']['terms']) && is_array($iconData['search']['terms'])) {
        $searchTerms = $iconData['search']['terms'];
    }

    $labelWords = preg_split('/[\s\-]+/', strtolower($label)) ?: [];
    $keyParts = preg_split('/[\-_]+/', strtolower($iconKey)) ?: [];

    $combined = array_merge($searchTerms, $labelWords, $keyParts);
    $combined = array_map('trim', $combined);
    $combined = array_filter($combined, function($v) { return $v !== '' && $v !== null; });
    $search = normalize_terms($combined ?: [$iconKey]);

    $output[$iconKey] = [
        'label' => $label,
        'search' => $search,
        'classname' => array_values(array_unique($classnames))
    ];
}

// Sort icons by key for deterministic output
ksort($output);

// Write to output file
$jsonOutput = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($outputPath, $jsonOutput);

echo "✅ Generated " . count($output) . " icons\n";
echo "📁 Output: {$outputPath}\n";
