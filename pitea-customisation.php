<?php

/**
 * Plugin Name:       Piteå Customisation
 * Description:       Custom functionality and modifications for the Piteå WordPress installation
 * Version:           1.0.0
 * Author:            Consid Borås AB
 * Text Domain:       pitea-customisation
 * Domain Path:       /languages
 */

namespace PiteaCustomisation;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PITEA_CUSTOMISATION_PATH', plugin_dir_path(__FILE__));
define('PITEA_CUSTOMISATION_URL', plugin_dir_url(__FILE__));
define('PITEA_CUSTOMISATION_VERSION', '1.0.0');

// Autoload classes
if (file_exists(PITEA_CUSTOMISATION_PATH . 'vendor/autoload.php')) {
    require_once PITEA_CUSTOMISATION_PATH . 'vendor/autoload.php';
}

// Initialize the plugin
new App();

// Modularity: Accessibility buttons module (sidebar placement; pair with ReadSpeaker → “Module placement”)
add_action(
    'init',
    static function (): void {
        if (!function_exists('modularity_register_module')) {
            return;
        }
        modularity_register_module(PITEA_CUSTOMISATION_PATH . 'source/php/Module/', 'AccButtons');
    },
    5
);

add_filter(
    '/Modularity/externalViewPath',
    static function (array $arr): array {
        $arr['mod-acc-buttons'] = PITEA_CUSTOMISATION_PATH . 'source/php/Module/views';

        return $arr;
    },
    10,
    3
);