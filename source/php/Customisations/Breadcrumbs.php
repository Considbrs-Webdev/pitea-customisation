<?php

namespace PiteaCustomisation\Customisations;

class Breadcrumbs
{
    public function __construct()
    {
        add_filter('sidebars_widgets', [$this, 'maybeHideBreadcrumbs'], 10, 1);
        add_filter('Municipio/Breadcrumbs/Items', [$this, 'replaceBreadcrumbIcon'], 100);
        add_filter('Municipio/Breadcrumbs/Items', [$this, 'changeHomeName'], 100);
    }

    /**
     * Hide breadcrumbs on front page if breadcrumbs block is used in slider area
     * 
     * @param array $sidebars
     * @return array
     */
    public function maybeHideBreadcrumbs($sidebars): array
    {
        if (!is_front_page() && apply_filters('Pitea/Breadcrumbs/Hide', false) === false) {
            return $sidebars;
        }

        if (empty($sidebars['slider-area']) || !is_array($sidebars['slider-area'])) {
            return $sidebars;
        }

        $widgetBlockOptions = \get_option('widget_block', []);

        foreach ($sidebars['slider-area'] as $key => $widgetId) {
            if (preg_match('/^block-(\d+)$/', (string) $widgetId, $matches)) {
                $id = (int) $matches[1];

                if (!empty($widgetBlockOptions[$id]['content']) && strpos($widgetBlockOptions[$id]['content'], 'acf/breadcrumbs') !== false) {
                    unset($sidebars['slider-area'][$key]);
                }
            }
        }

        $sidebars['slider-area'] = array_values($sidebars['slider-area']);

        return $sidebars;
    }

    /**
     * Replace all breadcrumb icons with a horizontal rule
     * 
     * @param array|null $items
     * @return array|null
     */
    public function replaceBreadcrumbIcon($items): array|null
    {
        if (!is_array($items)) {
            return $items;
        }

        foreach ($items as &$item) {
            $item['icon'] = 'horizontal_rule';
        }

        return $items;
    }

    /**
     * Change the home breadcrumb label to "Start"
     * 
     * @param array|null $items
     * @return array|null
     */
    public function changeHomeName($items): array|null
    {
        if (!is_array($items)) {
            return $items;
        }

        $keys = array_keys($items);
        if (!empty($keys)) {
            $firstKey = $keys[0];
            $items[$firstKey]['label'] = __('Start', 'pitea-customisation');
        }

        return $items;
    }
}
