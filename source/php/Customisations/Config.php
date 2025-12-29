<?php

namespace PiteaCustomisation\Customisations;

class Config
{
    /**
     * Initialize configuration and setup
     */
    public function __construct()
    {
        // Turn off ACF Extended Enhanced UI
        add_action('acfe/init', [$this, 'configureAcfExtended']);

        // Specifiy Font Awesome archive link icon for service information
        add_filter('Modularity/ServiceInformation/Module/ArchiveLink/Icon', [$this, 'setServiceInfoArchiveLinkIcon']);

        // Remove image from news item when used in posts module
        add_filter('ComponentLibrary/Component/NewsItem/Data', [$this, 'removeImageFromNewsItem']);
    }

    /**
     * Configure ACF Extended settings
     *
     * @return void
     */
    public function configureAcfExtended(): void
    {
        // Disable Enhanced UI
        acfe_update_setting('modules/ui', false);
    }

    public function setServiceInfoArchiveLinkIcon(): string
    {
        return 'fa-solid fa-arrow-right';
    }

    /**
     * Remove image from news item when used in posts module
     *
     * @param array $data
     * @return array
     */
    public function removeImageFromNewsItem(array $data): array
    {
        if (!in_array('module.posts.news-item', $data['context'])) {
            return $data;
        }

        $data['image'] = null;

        return $data;
    }
}
