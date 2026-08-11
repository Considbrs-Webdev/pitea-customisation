<?php

namespace PiteaCustomisation\Customisations;

/**
 * Code-owned brand tokens for Styleguide Design Builder (`theme_mod('tokens')`).
 *
 * Seeds and merges `source/tokens/pitea-brand.tokens.json` so brand globals win over
 * Customizer/DB edits while preserving non-brand component scopes from the database.
 */
class BrandTokens
{
    private const OPTION_VERSION = 'pitea_brand_tokens_version';

    private const FILE_VERSION = 2;

    private const TOKENS_RELATIVE_PATH = 'source/tokens/pitea-brand.tokens.json';

    /**
     * Cached decoded brand JSON from disk.
     *
     * @var array{token?: array<string, mixed>, component?: array<string, mixed>}|null
     */
    private ?array $brandTokens = null;

    /**
     * Register seed, merge, save-guard, and Design Builder lock hooks.
     */
    public function __construct()
    {
        add_action('init', [$this, 'maybeSeedThemeMod'], 5);
        add_filter('theme_mod_tokens', [$this, 'filterThemeModTokens']);
        add_filter('pre_set_theme_mod_tokens', [$this, 'guardThemeModTokens'], 10, 2);
        add_filter('Municipio/Styleguide/Customize/TokenData', [$this, 'lockBrandTokenFields']);
        add_filter('Municipio/Styleguide/CustomizeMarkup', [$this, 'maybeHideCustomizeMarkup'], 20);
    }

    /**
     * Persist merged brand tokens when the file version is ahead of the stored option.
     *
     * @return void
     */
    public function maybeSeedThemeMod(): void
    {
        $storedVersion = (int) get_option(self::OPTION_VERSION, 0);
        if ($storedVersion >= self::FILE_VERSION) {
            return;
        }

        $brand = $this->getBrandTokens();
        if ($brand === []) {
            return;
        }

        $existing = $this->decodeTokens(get_theme_mod('tokens', ''));
        $merged = $this->mergeTokens($existing, $brand);

        remove_filter('pre_set_theme_mod_tokens', [$this, 'guardThemeModTokens'], 10);
        set_theme_mod('tokens', wp_json_encode($merged));
        add_filter('pre_set_theme_mod_tokens', [$this, 'guardThemeModTokens'], 10, 2);

        update_option(self::OPTION_VERSION, self::FILE_VERSION, false);
    }

    /**
     * Ensure runtime reads always prefer code-owned brand keys.
     *
     * @param mixed $value Raw theme_mod value (JSON string or empty).
     * @return string JSON string expected by Municipio ApplyStyles.
     */
    public function filterThemeModTokens(mixed $value): string
    {
        $brand = $this->getBrandTokens();
        if ($brand === []) {
            return is_string($value) ? $value : wp_json_encode(['token' => [], 'component' => []]);
        }

        $existing = $this->decodeTokens($value);
        $merged = $this->mergeTokens($existing, $brand);

        return wp_json_encode($merged) ?: '{"token":{},"component":{}}';
    }

    /**
     * Re-apply protected brand keys when Customizer/API saves tokens.
     *
     * @param mixed $value New value being saved.
     * @param mixed $oldValue Previous theme_mod value.
     * @return string JSON string to persist.
     */
    public function guardThemeModTokens(mixed $value, mixed $oldValue): string
    {
        $brand = $this->getBrandTokens();
        if ($brand === []) {
            return is_string($value) ? $value : wp_json_encode(['token' => [], 'component' => []]);
        }

        $incoming = $this->decodeTokens($value);
        $merged = $this->mergeTokens($incoming, $brand);

        return wp_json_encode($merged) ?: '{"token":{},"component":{}}';
    }

    /**
     * Mark brand variables as locked in Design Builder field metadata.
     *
     * @param array<string, mixed> $tokenData Styleguide design-tokens.json structure.
     * @return array<string, mixed>
     */
    public function lockBrandTokenFields(array $tokenData): array
    {
        $brandKeys = array_keys($this->getBrandTokens()['token'] ?? []);
        if ($brandKeys === [] || !isset($tokenData['categories']) || !is_array($tokenData['categories'])) {
            return $tokenData;
        }

        $locked = array_fill_keys($brandKeys, true);

        foreach ($tokenData['categories'] as &$category) {
            if (!is_array($category) || !isset($category['settings']) || !is_array($category['settings'])) {
                continue;
            }

            foreach ($category['settings'] as &$setting) {
                if (!is_array($setting) || !isset($setting['variable']) || !is_string($setting['variable'])) {
                    continue;
                }

                if (isset($locked[$setting['variable']])) {
                    $setting['locked'] = true;
                }
            }
            unset($setting);
        }
        unset($category);

        return $tokenData;
    }

    /**
     * Hide Design Builder FAB markup for users without manage_options.
     *
     * @param string|null $markup Existing customize markup.
     * @return string|null
     */
    public function maybeHideCustomizeMarkup(?string $markup): ?string
    {
        if (current_user_can('manage_options')) {
            return $markup;
        }

        return null;
    }

    /**
     * Load and cache brand tokens from the plugin JSON file.
     *
     * @return array{token?: array<string, mixed>, component?: array<string, mixed>}
     */
    private function getBrandTokens(): array
    {
        if ($this->brandTokens !== null) {
            return $this->brandTokens;
        }

        $path = PITEA_CUSTOMISATION_PATH . self::TOKENS_RELATIVE_PATH;
        if (!is_readable($path)) {
            $this->brandTokens = [];
            return $this->brandTokens;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            $this->brandTokens = [];
            return $this->brandTokens;
        }

        $this->brandTokens = [
            'token' => is_array($decoded['token'] ?? null) ? $decoded['token'] : [],
            'component' => is_array($decoded['component'] ?? null) ? $decoded['component'] : [],
        ];

        return $this->brandTokens;
    }

    /**
     * Decode a theme_mod tokens payload into the expected array shape.
     *
     * @param mixed $value JSON string, array, or empty.
     * @return array{token: array<string, mixed>, component: array<string, mixed>}
     */
    private function decodeTokens(mixed $value): array
    {
        $default = ['token' => [], 'component' => []];

        if (is_array($value)) {
            return [
                'token' => is_array($value['token'] ?? null) ? $value['token'] : [],
                'component' => is_array($value['component'] ?? null) ? $value['component'] : [],
            ];
        }

        if (!is_string($value) || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return [
            'token' => is_array($decoded['token'] ?? null) ? $decoded['token'] : [],
            'component' => is_array($decoded['component'] ?? null) ? $decoded['component'] : [],
        ];
    }

    /**
     * Deep-merge overlay onto base so overlay keys win.
     *
     * @param array{token: array<string, mixed>, component: array<string, mixed>} $base
     * @param array{token?: array<string, mixed>, component?: array<string, mixed>} $overlay
     * @return array{token: array<string, mixed>, component: array<string, mixed>}
     */
    private function mergeTokens(array $base, array $overlay): array
    {
        return [
            'token' => $this->deepMerge(
                is_array($base['token'] ?? null) ? $base['token'] : [],
                is_array($overlay['token'] ?? null) ? $overlay['token'] : []
            ),
            'component' => $this->deepMerge(
                is_array($base['component'] ?? null) ? $base['component'] : [],
                is_array($overlay['component'] ?? null) ? $overlay['component'] : []
            ),
        ];
    }

    /**
     * Recursively merge associative arrays; overlay scalar/list values replace base.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && $this->isAssociative($value)
                && $this->isAssociative($base[$key])
            ) {
                $base[$key] = $this->deepMerge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<mixed> $array
     */
    private function isAssociative(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
