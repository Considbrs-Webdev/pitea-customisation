<?php
namespace PiteaCustomisation\Customisations;

/*
 * Handles custom taxonomies for the Piteå WordPress installation.
 * Taxonomies are defined and managed via ACF JSON files located in the 'taxonomies' directory.
 * Use the UI to edit the taxonomies, and they will be automatically saved to and loaded from the specified directory.
 * Should only be edited in development environments, as changes will be overwritten in production when ACF syncs the taxonomies.
 */
class Taxonomies {
    public function __construct() {
        // Loads and registers taxonomies via ACF
        add_filter('acf/settings/load_json', function ($path) {
            if (empty($path) || !is_array($path)) {
                $path = [];
            }

            $path[] = PITEA_CUSTOMISATION_PATH . 'taxonomies';

            return $path;
        });
        
        // Saves taxonomies to the same directory
        add_filter('acf/settings/save_json/type=acf-taxonomy', function ($path) {
            return PITEA_CUSTOMISATION_PATH . 'taxonomies';
        });
    }
}