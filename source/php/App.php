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
            Customisations\Headers::class,
            Customisations\News::class,
            Customisations\Pagination::class,
            Customisations\ShareButton::class,
            Customisations\Modules\InlayList::class,
            Customisations\Modules\Container::class,
            Customisations\Modules\Posts::class,
            Customisations\Modules\Text::class,
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
        // Enqueue main JS
        $file = Helpers\CacheBust::getFile('source/js/main.js');
        if ($file) {
            wp_enqueue_script(
                'pitea-customisation-main',
                $file,
                [],
                PITEA_CUSTOMISATION_VERSION,
                true
            );
        }

        // Enqueue main CSS
        $file = Helpers\CacheBust::getFile('source/sass/style.scss');
        if ($file) {
            wp_enqueue_style(
                'pitea-customisation-style',
                $file,
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
        // Enqueue admin JS
        $file = Helpers\CacheBust::getFile('source/js/admin.js');
        if ($file) {
            wp_enqueue_script(
                'pitea-customisation-admin',
                $file,
                [],
                PITEA_CUSTOMISATION_VERSION,
                true
            );
        }

        // Enqueue admin CSS
        $file = Helpers\CacheBust::getFile('source/sass/admin.scss');
        if ($file) {
            wp_enqueue_style(
                'pitea-customisation-admin-style',
                $file,
                [],
                PITEA_CUSTOMISATION_VERSION
            );
        }
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
