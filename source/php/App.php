<?php

namespace PiteaCustomisation;

class App
{
    /**
     * Registered customisation instances
     *
     * @var object[]
     */
    private array $instances = [];

    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        $this->registerInstances();
        $this->initHooks();
    }

    /**
     * Register all customisation class instances
     *
     * Add your custom classes here to have them instantiated automatically.
     *
     * @return void
     */
    private function registerInstances(): void
    {
        $classes = [
            Customisations\Accessibility::class,
            Customisations\Breadcrumbs::class,
            Customisations\Config::class,
            Customisations\Decorators::class,
            Customisations\FontAwesome::class,
            Customisations\ColorPicker::class,
            Customisations\News::class,
            Customisations\Pagination::class,
            Customisations\Modules\InlayList::class,
            Customisations\Modules\Container::class,
            Customisations\Modules\Posts::class,
            Customisations\Modules\Text::class,
            Customisations\ShareButton::class,
            // Add more customisation classes here
            // Customisations\YourCustomClass::class,
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $this->instances[] = new $class();
            }
        }
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function initHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        $manifest = $this->getManifest();

        if (!$manifest) {
            return;
        }

        // Enqueue main JS
        if (isset($manifest['source/js/main.js'])) {
            wp_enqueue_script(
                'pitea-customisation-main',
                PITEA_CUSTOMISATION_URL . 'dist/' . $manifest['source/js/main.js']['file'],
                [],
                PITEA_CUSTOMISATION_VERSION,
                true
            );
        }

        // Enqueue main CSS
        if (isset($manifest['source/sass/style.scss'])) {
            wp_enqueue_style(
                'pitea-customisation-style',
                PITEA_CUSTOMISATION_URL . 'dist/' . $manifest['source/sass/style.scss']['file'],
                [],
                PITEA_CUSTOMISATION_VERSION
            );
        }
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueueAdminAssets(): void
    {
        $manifest = $this->getManifest();

        if (!$manifest) {
            return;
        }

        // Enqueue admin JS
        if (isset($manifest['source/js/admin.js'])) {
            wp_enqueue_script(
                'pitea-customisation-admin',
                PITEA_CUSTOMISATION_URL . 'dist/' . $manifest['source/js/admin.js']['file'],
                [],
                PITEA_CUSTOMISATION_VERSION,
                true
            );
        }

        // Enqueue admin CSS
        if (isset($manifest['source/sass/admin.scss'])) {
            wp_enqueue_style(
                'pitea-customisation-admin-style',
                PITEA_CUSTOMISATION_URL . 'dist/' . $manifest['source/sass/admin.scss']['file'],
                [],
                PITEA_CUSTOMISATION_VERSION
            );
        }
    }

    /**
     * Get the Vite manifest
     *
     * @return array|null
     */
    private function getManifest(): ?array
    {
        $manifestPath = PITEA_CUSTOMISATION_PATH . 'dist/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            return null;
        }

        $manifest = file_get_contents($manifestPath);

        return json_decode($manifest, true);
    }

    /**
     * Get a registered instance by class name
     *
     * @param string $className
     * @return object|null
     */
    public function getInstance(string $className): ?object
    {
        foreach ($this->instances as $instance) {
            if ($instance instanceof $className) {
                return $instance;
            }
        }

        return null;
    }
}
