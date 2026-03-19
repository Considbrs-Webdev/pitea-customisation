<?php

declare(strict_types=1);

namespace PiteaCustomisation\Admin\Tabs;

use PiteaCustomisation\Admin\SettingsTabInterface;

/**
 * Class GeneralTab
 *
 * General settings tab for the Piteå Customisation plugin.
 */
class GeneralTab implements SettingsTabInterface
{
    private const OPTION_GROUP = 'pitea_customisation_general';

    private const GROUP_INTRO = 'pitea_customisation_group_intro';

    // -------------------------------------------------------------------------
    // SettingsTabInterface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return 'general';
    }

    public function getTitle(): string
    {
        return __('General settings', 'pitea-customisation');
    }

    public function getOptionGroup(): string
    {
        return self::OPTION_GROUP;
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    public function register(): void
    {
        $this->registerIntroGroup();
    }

    private function registerIntroGroup(): void
    {
        add_settings_section(
            'pitea_customisation_intro',
            __('Overview', 'pitea-customisation'),
            function (): void {
                echo '<p class="pitea-settings__section-desc">' . esc_html__(
                    'Here you will find settings for Piteå kommun\'s homepage.',
                    'pitea-customisation'
                ) . '</p>';
            },
            self::GROUP_INTRO
        );
    }

    

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function save(array $data): true|\WP_Error
    {
        return true;
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function render(): void
    {
        $this->renderGroup(__('General settings', 'pitea-customisation'), self::GROUP_INTRO);
    }

    private function renderGroup(string $title, string $groupPageSlug): void
    {
        ?>
        <div class="pitea-settings__card">
            <div class="pitea-settings__card-header">
                <h2 class="pitea-settings__card-title"><?php echo esc_html($title); ?></h2>
            </div>
            <div class="pitea-settings__card-body">
                <?php do_settings_sections($groupPageSlug); ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function sanitizeNoticeType(string $value): string
    {
        return in_array($value, ['info', 'warning', 'danger'], true) ? $value : 'info';
    }
}
