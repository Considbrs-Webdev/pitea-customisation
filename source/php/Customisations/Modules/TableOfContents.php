<?php

namespace PiteaCustomisation\Customisations\Modules;

class TableOfContents
{
    public function __construct()
    {
        add_filter('Modularity/Module/TableOfContents/SidebarChoices', [$this, 'addSidebarChoices']);
        add_filter('Modularity/Module/TableOfContents/SidebarSelectorMap', [$this, 'addSidebarSelectorMap']);
    }

    /**
     * Register custom sidebar choices for the TOC module field.
     *
     * @param array $choices
     * @return array
     */
    public function addSidebarChoices(array $choices): array
    {
        $choices['sidebar-footer-area'] = __('Sidebar footer area', 'pitea-customisation');

        return $choices;
    }

    /**
     * Map custom sidebar keys to their DOM selectors for the TOC JS.
     *
     * @param array $map
     * @return array
     */
    public function addSidebarSelectorMap(array $map): array
    {
        $map['sidebar-footer-area'] = '.s-sidebar-footer-area';

        return $map;
    }
}
