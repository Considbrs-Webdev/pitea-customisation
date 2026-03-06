<?php

namespace PiteaCustomisation\Customisations;

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
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorFontOverride']);

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

        // Enqueue admin CSS
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
     * Add styles to TinyMCE editor (classic editor iframe)
     *
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
     * Override WordPress block editor reset font in Gutenberg.
     * Core uses html :where(.editor-styles-wrapper) { font-family: serif; } which can
     * load after the theme styleguide in production. This rule uses higher specificity
     * (html .editor-styles-wrapper) so the theme font wins in all environments.
     *
     * @return void
     */
    public function enqueueBlockEditorFontOverride(): void
    {
        wp_register_style(
            'pitea-block-editor-font-override',
            false,
            ['block-editor-municipio']
        );
        wp_enqueue_style('pitea-block-editor-font-override');
        $css = 'html .editor-styles-wrapper { font-family: var(--font-family-base, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif); }';
        wp_add_inline_style('pitea-block-editor-font-override', $css);
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
     * @return array<string, mixed>
     */
    public function removeParagraphColorAndBackground(array $args, string $block_name): array
    {
        if ($block_name === 'core/paragraph') {
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
