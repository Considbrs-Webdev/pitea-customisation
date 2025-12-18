<?php
/**
 * Generate FontAwesome icons list from icons.json
 * 
 * This script reads the FontAwesome icons.json file and generates
 * a simplified JSON file containing only free icons with their
 * proper class combinations (e.g., "fa-solid fa-dna").
 */

$iconsJsonPath = $argv[1] ?? '/Users/michaelclaesson/Desktop/icons.json';
$outputPath = __DIR__ . '/data/fontawesome-icons.json';

// Read and decode the icons JSON file
$iconsData = json_decode(file_get_contents($iconsJsonPath), true);

if (!$iconsData) {
    die("Error: Could not read or parse icons.json\n");
}

$freeIcons = [];

// Process each icon
foreach ($iconsData as $iconKey => $iconData) {
    // Skip if not an array (handles the numeric keys at the start)
    if (!is_array($iconData)) {
        continue;
    }
    
    // Check if icon has free styles
    if (!isset($iconData['free']) || empty($iconData['free'])) {
        continue;
    }
    
    $label = $iconData['label'] ?? ucfirst(str_replace('-', ' ', $iconKey));
    $freeStyles = $iconData['free'];
    
    // Generate class combinations for each free style
    foreach ($freeStyles as $style) {
        $className = "fa-{$style} fa-{$iconKey}";
        $freeIcons[$className] = $label;
    }
}

// Sort by label for easier browsing
asort($freeIcons);

// Write to output file
$jsonOutput = json_encode($freeIcons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($outputPath, $jsonOutput);

echo "✅ Generated " . count($freeIcons) . " free icons\n";
echo "📁 Output: {$outputPath}\n";
