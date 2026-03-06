<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin;

/**
 * Interface SettingsTabInterface
 *
 * Implement this interface to add a new tab to the plugin settings page.
 */
interface SettingsTabInterface
{
    /**
     * Unique tab identifier used as the `tab` URL parameter value.
     */
    public function getId(): string;

    /**
     * Human-readable tab title shown in the tab navigation.
     */
    public function getTitle(): string;

    /**
     * The option group passed to settings_fields() for this tab's form.
     *
     * All options registered via register_setting() in register() must
     * use this same option group so they are saved when the form is submitted.
     */
    public function getOptionGroup(): string;

    /**
     * Register settings, sections, and fields for this tab.
     *
     * Called on the admin_init hook. Use register_setting(), add_settings_section(),
     * and add_settings_field() here.
     */
    public function register(): void;

    /**
     * Render the tab contents (groups, sections, and fields).
     *
     * Called inside the tab panel <div>. Use do_settings_sections() to
     * output registered settings sections and fields.
     */
    public function render(): void;

    /**
     * Save this tab's options from raw POST data.
     *
     * Called by the AJAX handler after nonce verification. Implementations
     * should sanitize each value and call update_option(). Return a WP_Error
     * on failure or true on success.
     *
     * @param  array<string, mixed> $data Raw POST data (already slashed by WordPress).
     * @return true|\WP_Error
     */
    public function save(array $data): true|\WP_Error;
}
