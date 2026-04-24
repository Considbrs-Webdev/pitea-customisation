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
        $this->initHooks();
        $this->registerInstances();
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
            Admin\Settings::class,
            Customisations\Accessibility::class,
            Customisations\Breadcrumbs::class,
            Customisations\BlockPatternCompatibility::class,
            Customisations\ColorPicker::class,
            Customisations\Config::class,
            Customisations\CustomerFeedback::class,
            Customisations\Dashboard::class,
            Customisations\Decorators::class,
            Customisations\ExternalContent::class,
            Customisations\FontAwesome::class,
            Customisations\Headers::class,
            Customisations\Matomo::class,
            Customisations\Navigation::class,
            Customisations\News::class,
            Customisations\NewsTemplate::class,
            Customisations\Pagination::class,
            Customisations\Permissions::class,
            Customisations\Policies::class,
            Customisations\PostTypes::class,
            Customisations\Search::class,
            Customisations\ShareButton::class,
            Customisations\Taxonomies::class,
            Customisations\Templates::class,
            Customisations\Typesense::class,
            Customisations\Admin\Admin::class,
            Customisations\Admin\PostTemplates::class,
            Customisations\Modules\InlayList::class,
            Customisations\Modules\Container::class,
            Customisations\Modules\LinkCards::class,
            Customisations\Modules\Posts::class,
            Customisations\Modules\Text::class,
            Customisations\Modules\QuickLinks::class,
            Customisations\Modules\ManualInput::class,
            Customisations\Modules\Slider::class,
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
