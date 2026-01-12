<?php

namespace PiteaCustomisation\Customisations\Modules;

class Container
{
    /**
     * Initialize Container customisations
     */
    public function __construct()
    {
        add_filter('Municipio/Block/Container/contentClassList', [$this, 'removeContainerSpacingClass'], 10, 3);
    }

    /**
     * Remove o-container--remove-spacing class from container block
     * Only keeps it if the container is set to full width
     *
     * @param string $classList The content class list
     * @param array $data The block data
     * @param array $block The block array
     * @return string Modified class list
     */
    public function removeContainerSpacingClass(string $classList, array $data, array $block): string
    {
        // Only keep o-container--remove-spacing if block is full width
        $isFullWidth = isset($block['align']) && $block['align'] === 'full';

        if ($isFullWidth) {
            // Remove the class if not full width
            return str_replace('o-container--remove-spacing', '', $classList);
        }

        // Keep the class if full width
        return $classList;
    }
}
