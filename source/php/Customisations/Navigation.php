<?php

namespace PiteaCustomisation\Customisations;

class Navigation
{
    /**
     * Initialize navigation customisations
     */
    public function __construct()
    {
        add_filter('ComponentLibrary/Component/Button/Class', [$this, 'setTabMenuButtonSize'], 10, 2);
    }

    /**
     * Force tab menu buttons to medium size (override default small)
     *
     * @param string[] $classes Button CSS classes
     * @param array<string, mixed> $context Component context including 'component.nav.button'
     * @return string[]
     */
    public function setTabMenuButtonSize(array $classes, array $context): array
    {
        if (!in_array('component.nav.button', $context)) {
            return $classes;
        }

        $classes = array_filter($classes, fn (string $class) => $class !== 'c-button--sm');
        $classes[] = 'c-button--md';

        return $classes;
    }
}
