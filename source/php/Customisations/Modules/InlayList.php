<?php
namespace PiteaCustomisation\Customisations\Modules;

class InlayList
{
    /**
     * Initialize Inlay List customisations
     */
    public function __construct()
    {
        // Customisations for Inlay List can be added here in the future
        add_filter('Modularity/Display/mod-inlaylist/viewData', [$this, 'modifyInlayListData'], 10, 1);
    }

    public function modifyInlayListData(array $data): array
    {
        foreach ($data['items'] as &$item) {
            if (!isset($item['icon'])) {
                if ($this->isExternalLink($item['href'])) {
                    $item['icon'] = 'fa-solid fa-arrow-up-right-from-square';
                } else {
                    $item['icon'] = 'fa-solid fa-arrow-right';
                }
            }
        }

        return $data;
    }

    /**
     * Check if a URL is an external link
     * 
     * @param string $url The URL to check
     * @return bool True if the URL is external, false otherwise
     */
    private function isExternalLink(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Parse the URL
        $urlHost = parse_url($url, PHP_URL_HOST);
        
        // If no host is found, it's a relative URL (internal)
        if (!$urlHost) {
            return false;
        }

        // Get the current site's host
        $siteHost = parse_url(home_url(), PHP_URL_HOST);

        // Compare hosts
        return $urlHost !== $siteHost;
    }
}