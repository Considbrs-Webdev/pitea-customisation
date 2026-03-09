<?php
namespace PiteaCustomisation\Customisations;

/*
 * Handles custom post types for the Piteå WordPress installation.
 * Post types are defined and managed via ACF JSON files located in the 'post-types' directory.
 * Use the UI to edit the post types, and they will be automatically saved to and loaded from the specified directory.
 * Should only be edited in development environments, as changes will be overwritten in production when ACF syncs the post types.
 */
class PostTypes {
    public function __construct() {
        // Loads and registers post types via ACF
        add_filter('acf/settings/load_json', function ($path) {
            if (empty($path) || !is_array($path)) {
                $path = [];
            }

            $path[] = PITEA_CUSTOMISATION_PATH . 'post-types';

            return $path;
        });
        
        // Saves post types to the same directory in dev environment
        if (wp_get_environment_type() === 'development') {
            add_filter('acf/settings/save_json/type=acf-post-type', function ($path) {
                return PITEA_CUSTOMISATION_PATH . 'post-types';
            });
        }
    }
}