<?php

namespace PiteaCustomisation\Customisations\Modules;

class Container
{
    /**
     * Initialize Container customisations
     */
    public function __construct()
    {
        // Customisations for Container can be added here in the future
        add_filter('Municipio/Block/Container/contentClassList', [$this, 'removeContainerSpacingClass'], 10, 3);
    }

    /**
     * Remove o-container--remove-spacing class from container block
     *
     * @param string $classList The content class list
     * @param array $data The block data
     * @param array $block The block array
     * @return string Modified class list
     */
    public function removeContainerSpacingClass(string $classList, array $data, array $block): string
    {
        return str_replace('o-container--remove-spacing', '', $classList);
    }
}
