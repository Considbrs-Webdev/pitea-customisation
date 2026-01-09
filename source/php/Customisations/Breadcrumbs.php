<?php

namespace PiteaCustomisation\Customisations;

class Breadcrumbs
{
    public function __construct()
    {
        add_filter('Municipio/Breadcrumbs/Items', [$this, 'replaceBreadcrumbIcon'], 100);
        add_filter('Municipio/Breadcrumbs/Items', [$this, 'changeHomeName'], 100);
    }

    /**
     * Replace all breadcrumb icons with a horizontal rule
     */
    public function replaceBreadcrumbIcon($items): array
    {
        foreach ($items as &$item) {
            $item['icon'] = 'horizontal_rule';
        }

        return $items;
    }

    /**
     * Change the home breadcrumb label to "Start"
     */
    public function changeHomeName($items): array
    {
        $keys = array_keys($items);
        if (!empty($keys)) {
            $firstKey = $keys[0];
            $items[$firstKey]['label'] = __('Start', 'pitea-customisation');
        }

        return $items;
    }
}
