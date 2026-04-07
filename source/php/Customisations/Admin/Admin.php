<?php

namespace PiteaCustomisation\Customisations\Admin;

use PiteaCustomisation\Helpers\CacheBust;

class Admin
{
    /**
     * Register admin and block editor hooks
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('init', [$this, 'addEditorStyles']);
        add_action('after_setup_theme', [$this, 'registerEditorFontSizes'], 20);
        add_action('init', [$this, 'registerParagraphBlockStyles'], 20);

        // Editor behaviour
        add_action('admin_init', [$this, 'removeEditorBlockDirectoryAssets']);
        add_filter('theme_page_templates', [$this, 'removePageCenteredTemplate'], 100, 1);
        add_filter('register_block_type_args', [$this, 'removeParagraphColorAndBackground'], 10, 2);
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueueAdminAssets(): void
    {
        // Enqueue admin JS
        $file = CacheBust::getFile('source/js/admin.js');
        if ($file) {
            wp_enqueue_script(
                'pitea-customisation-admin',
                $file,
                [],
                PITEA_CUSTOMISATION_VERSION,
                true
            );
        }

        // Enqueue admin CSS on all admin pages (sidebar, edit screen frame, settings, etc.)
        $file = CacheBust::getFile('source/sass/admin.scss');
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
     * Add admin.scss to the block editor and classic editor content area via add_editor_style().
     * @return void
     */
    public function addEditorStyles(): void
    {
        $file = CacheBust::getFile('source/sass/admin.scss');
        if ($file) {
            add_editor_style($file);
        }
    }
    /**
     * Register block editor font size presets to match the pitea design system.
     * WordPress default "small" is 13px; pitea uses 16px (--font-size-small).
     * Running at priority 20 ensures this runs after the theme's after_setup_theme.
     *
     * @return void
     */
    public function registerEditorFontSizes(): void
    {
        add_theme_support('editor-font-sizes', [
            [
                'name' => _x('Small', 'Editor font size preset', 'pitea-customisation'),
                'slug' => 'small',
                'size' => '16px',
            ],
            [
                'name' => _x('Medium', 'Editor font size preset', 'pitea-customisation'),
                'slug' => 'medium',
                'size' => '18px',
            ],
            [
                'name' => _x('Large', 'Editor font size preset', 'pitea-customisation'),
                'slug' => 'large',
                'size' => '22px',
            ],
            [
                'name' => _x('Extra large', 'Editor font size preset', 'pitea-customisation'),
                'slug' => 'x-large',
                'size' => '32px',
            ],
            [
                'name' => _x('Larger', 'Editor font size preset', 'pitea-customisation'),
                'slug' => 'larger',
                'size' => '42px',
            ],
        ]);
    }

    /**
     * Styles live in general/blocks.scss (main stylesheet + admin/editor via add_editor_style).
     *
     * @return void
     */
    public function registerParagraphBlockStyles(): void
    {
        register_block_style('core/paragraph', [
            'name'  => 'preamble',
            'label' => _x('Preamble', 'Paragraph block style', 'pitea-customisation'),
        ]);
    }

    /**
     * Remove block directory assets from the editor
     *
     * @return void
     */
    public function removeEditorBlockDirectoryAssets(): void
    {
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
    }

    /**
     * Remove the page-centered template from the available page templates in the editor
     *
     * @param array<string, string> $templates
     * @return array<string, string>
     */
    public function removePageCenteredTemplate(array $templates): array
    {
        unset($templates['page-centered.blade.php']);
        return $templates;
    }

    /**
     * Remove color and background color support from the paragraph block
     *
     * @param array<string, mixed> $args
     * @param string $block_name
     * @return array<string, mixed>
     */
    public function removeParagraphColorAndBackground(array $args, string $block_name): array
    {
        if ($block_name === 'core/paragraph' || $block_name === 'core/heading') {
            if (!isset($args['supports']['color']) || !is_array($args['supports']['color'])) {
                $args['supports']['color'] = [];
            }

            $args['supports']['color']['text']        = false;
            $args['supports']['color']['background'] = false;
            $args['supports']['color']['gradients']   = false;
            $args['supports']['color']['link']        = false;

            if (isset($args['supports']['__experimentalBorder'])) {
                $args['supports']['__experimentalBorder']['color'] = false;
            }
        }

        return $args;
    }
}
