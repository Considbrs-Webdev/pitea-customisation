<?php

namespace PiteaCustomisation\Customisations;

/**
 * Skips Modularity's module-usage scan on screens that are not modules.
 *
 * ModuleManager::whereUsedMetaBox() searches every post_content for a
 * shortcode before it limits the metabox to module post types. Module
 * screens keep that callback. Remove this class when the theme stops
 * running the scan for other post types.
 */
class ModuleUsageMetabox
{
    public function __construct()
    {
       add_action('current_screen', [$this, 'unhookUsageScanOutsideModules']);
    }

    /**
     * Remove the theme callback unless the current screen is an enabled module.
     */
    public function unhookUsageScanOutsideModules(\WP_Screen $screen): void
    {
        if (!class_exists(\Modularity\App::class) || !class_exists(\Modularity\ModuleManager::class)) {
            return;
        }

        $manager = \Modularity\App::$moduleManager ?? null;
        if (!$manager instanceof \Modularity\ModuleManager) {
            return;
        }

        $postType = (string) $screen->post_type;
        if ($postType !== '' && in_array($postType, \Modularity\ModuleManager::$enabled, true)) {
            return;
        }

        remove_action('add_meta_boxes', [$manager, 'whereUsedMetaBox']);
    }
}
