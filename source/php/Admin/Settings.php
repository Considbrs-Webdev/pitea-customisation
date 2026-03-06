<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin;

use PiteaCustomisation\Admin\Tabs\ExternalContentTab;
use PiteaCustomisation\Helpers\CacheBust;

/**
 * Class Settings
 *
 * Registers and renders the Piteå Customisation settings page under
 * Settings → Piteå Customisation in the WordPress admin menu.
 *
 * Tabs are registered by adding SettingsTabInterface implementations to
 * the $tabs array in the constructor. Each tab manages its own sections,
 * fields, option registration, and AJAX save handling.
 */
class Settings
{
    private const PAGE_SLUG  = 'pitea-customisation';
    private const AJAX_ACTION = 'pitea_customisation_save_settings';
    private const NONCE_ACTION = 'pitea_customisation_settings_nonce';

    /** @var SettingsTabInterface[] */
    private array $tabs;

    public function __construct()
    {
        $this->tabs = [
            new ExternalContentTab(),
        ];

        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handleAjaxSave']);
    }

    // -------------------------------------------------------------------------
    // WordPress hooks
    // -------------------------------------------------------------------------

    public function addMenuPage(): void
    {
        add_options_page(
            __('Piteå Customisation', 'pitea-customisation'),
            __('Piteå Customisation', 'pitea-customisation'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        foreach ($this->tabs as $tab) {
            $tab->register();
        }
    }

    /**
     * Enqueue settings page assets and pass config to JS — only on our page.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        // The admin script (admin.js) is already enqueued globally by App.php.
        // We just need to localize it with the data our settings JS needs.
        wp_localize_script(
            'pitea-customisation-admin',
            'piteaSettings',
            [
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'action'     => self::AJAX_ACTION,
                'nonce'      => wp_create_nonce(self::NONCE_ACTION),
                'i18n'       => [
                    'saving'  => __('Saving…', 'pitea-customisation'),
                    'saved'   => __('Changes saved', 'pitea-customisation'),
                    'error'   => __('Could not save settings', 'pitea-customisation'),
                ],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // AJAX save handler
    // -------------------------------------------------------------------------

    public function handleAjaxSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'pitea-customisation')], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'pitea-customisation')], 403);
        }

        $tabId   = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : '';
        $activeTab = null;

        foreach ($this->tabs as $tab) {
            if ($tab->getId() === $tabId) {
                $activeTab = $tab;
                break;
            }
        }

        if ($activeTab === null) {
            wp_send_json_error(['message' => __('Unknown tab.', 'pitea-customisation')], 400);
        }

        $result = $activeTab->save($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Settings saved.', 'pitea-customisation')]);
    }

    // -------------------------------------------------------------------------
    // Page rendering
    // -------------------------------------------------------------------------

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Resolve active tab (first by default).
        $currentTabId = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $activeTab    = $this->tabs[0];

        foreach ($this->tabs as $tab) {
            if ($tab->getId() === $currentTabId) {
                $activeTab = $tab;
                break;
            }
        }
        ?>
        <div class="pitea-settings" data-active-tab="<?php echo esc_attr($activeTab->getId()); ?>">

            <!-- Header -->
            <div class="pitea-settings__header">
                <div class="pitea-settings__header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                </div>
                <div class="pitea-settings__header-actions">
                    <span class="pitea-settings__save-status" aria-live="polite"></span>
                    <button type="button" class="pitea-settings__save-btn button button-primary">
                        <?php esc_html_e('Save Changes', 'pitea-customisation'); ?>
                    </button>
                </div>
            </div>

            <!-- Body: vertical tab nav + content -->
            <div class="pitea-settings__body">

                <!-- Vertical tab navigation -->
                <nav class="pitea-settings__nav" aria-label="<?php esc_attr_e('Settings sections', 'pitea-customisation'); ?>">
                    <?php foreach ($this->tabs as $tab) : ?>
                        <a
                            href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=' . $tab->getId())); ?>"
                            class="pitea-settings__nav-item<?php echo $tab->getId() === $activeTab->getId() ? ' is-active' : ''; ?>"
                            data-tab="<?php echo esc_attr($tab->getId()); ?>"
                        >
                            <?php echo esc_html($tab->getTitle()); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Tab content -->
                <div class="pitea-settings__content">
                    <?php foreach ($this->tabs as $tab) : ?>
                        <div
                            class="pitea-settings__tab<?php echo $tab->getId() === $activeTab->getId() ? ' is-active' : ''; ?>"
                            id="pitea-settings-tab-<?php echo esc_attr($tab->getId()); ?>"
                            data-tab="<?php echo esc_attr($tab->getId()); ?>"
                        >
                            <?php $tab->render(); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /.pitea-settings__body -->

        </div><!-- /.pitea-settings -->
        <?php
    }
}
