<?php

namespace PiteaCustomisation\Customisations\Modules;

class Container
{
    /**
     * Initialize Container customisations
     */
    public function __construct()
    {
        // Use render_block filter to intercept Container block output
        add_filter('render_block', [$this, 'modifyContainerBlockOutput'], 10, 2);
    }

    /**
     *
     * @param string $blockContent The rendered block content
     * @param array $block The block array
     * @return string Modified block content
     */
    public function modifyContainerBlockOutput(string $blockContent, array $block): string
    {
        // Only process Container blocks
        if (!isset($block['blockName']) || $block['blockName'] !== 'acf/container') {
            return $blockContent;
        }
        // Check if block is full width
        $isFullWidth = isset($block['attrs']['align']) && $block['attrs']['align'] === 'full';

        // Only remove o-container--remove-spacing if block is full width
        if ($isFullWidth) {
            $blockContent = preg_replace_callback(
                '/(<div[^>]*class=["\'])([^"\']*)(["\'])/',
                function ($matches) {
                    $classes = preg_split('/\s+/', trim($matches[2]));
                    $classes = array_filter($classes, function ($class) {
                        return $class !== 'o-container--remove-spacing';
                    });
                    $newClasses = implode(' ', $classes);
                    return $matches[1] . $newClasses . $matches[3];
                },
                $blockContent
            );
        }

        return $blockContent;
    }
}
