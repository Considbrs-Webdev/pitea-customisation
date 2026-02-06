<?php
namespace PiteaCustomisation\Helpers;

class CacheBust
{
    private static ?array $manifest = null;

    /**
     * Get a cache-busted URL for a given asset
     *
     * @param string $assetPath The path to the asset file
     * @return string The cache-busted URL
     */
    public static function getFile(string $assetPath): string
    {
        $assetPath = ltrim($assetPath, '/');
        
        $manifest = self::getManifest();
        $file = $manifest[$assetPath]['file'] ?? $assetPath;

        $pluginRootFile = dirname(__DIR__, 3) . '/index.php';
        $base = plugin_dir_url($pluginRootFile);

        return $base . 'dist/' . $file;
    }

    private static function getManifest(): array
    {
        if (self::$manifest === null) {
            $manifestPath = dirname(__DIR__, 3) . '/dist/.vite/manifest.json';
            self::$manifest = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true) ?? []
                : [];
        }
        return self::$manifest;
    }
}