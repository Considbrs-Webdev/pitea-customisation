<?php

namespace PiteaCustomisation\Customisations;

/**
 * Skips Modularity's module-usage scan on screens that are not modules.
 *
 * @upstream-shim id=modularity-where-used-metabox
 * @upstream-repo municipio (Modularity in theme, ModuleManager)
 * @upstream-broken whereUsedMetaBox() calls getModuleUsage() on every add_meta_boxes before limiting to module post types (full post_content LIKE scan).
 * @upstream-fix-needed Early return unless current post type is in ModuleManager::$enabled.
 * @upstream-fixed-in municipio@7.55.8
 * @remove-when Deployed Municipio theme version is >= 7.55.8; delete this class and App.php registration.
 * @verify-removal Edit a page: no SQL containing `[modularity id=`. Edit a mod-* module: usage metabox still appears.
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
