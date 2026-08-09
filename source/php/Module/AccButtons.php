<?php

declare(strict_types=1);

namespace PiteaCustomisation\Module;

use Modularity\Module;
use PiteaCustomisation\Customisations\Accessibility;

/**
 * Modularity module: Listen + Print (same actions as the nav-helper accessibility bar).
 * Use with “Hide nav bar buttons” in Settings → Piteå → ReadSpeaker to avoid duplicates.
 */
class AccButtons extends Module
{
    public $slug = 'acc-buttons';

    public $icon = 'background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEyIDN2OS4yOGMuNzEuNDQgMS41LjY4IDIuNS42OCAxLjUgMCAyLjUtLjUgMy4yLTEuMi43LS43IDEuMy0xLjUgMS4zLTMuMjhWNGMwLTEuNS0uNS0yLjUtMS4yLTMuMi0uNy0uNy0xLjUtMS4zLTMuMy0xLjMtMS45IDAtMyAuNi0zLjggMS41LS44LjktMS4yIDIuMS0xLjIgMy41em0tNyA5djIuMjhjMCAyLjUgMSAyLjUgMiAyIDIuNSAxLjUgMi41IDMuNSAyLjUgNS41VjE5YzAtLjUtLjUtMS0xLTFIN2MtLjUgMC0xIC41LTEgMXYtNGMwLTEuNS41LTIuNSAxLjItMy4yLjctLjcgMS41LTEuMyAzLjMtMS4zLjkgMCAxLjcuMiAyLjUuNnpNMjEgMTJ2N2MwIC41LS41IDEtMSAxaC0xYy0uNSAwLTEtLjUtMS0xdi00YzAtMS41LS41LTIuNS0xLjItMy4yLS43LS43LTEuNS0xLjMtMy4zLTEuMy0uOSAwLTEuNy4yLTIuNS42VjNjMC0xLjUuNS0yLjUgMS4yLTMuMi43LS43IDEuNS0xLjMgMy4zLTEuMyAxLjkgMCAzIC42IDMuOCAxLjUuOC45IDEuMiAyLjEgMS4yIDMuNXoiIGZpbGw9IiNlOGVhZWQiLz48L3N2Zz4=);';

    public $supports = [];

    public $isBlockCompatible = true;

    public function init(): void
    {
        $this->nameSingular = __('Accessibility buttons', 'pitea-customisation');
        $this->namePlural   = __('Accessibility buttons', 'pitea-customisation');
        $this->description  = __('Listen and print buttons (same as the nav bar). Place in a sidebar for layout control.', 'pitea-customisation');
    }

    public function data(): array
    {
        $items  = Accessibility::getAccessibilityMenuItemsSnapshot();
        $fields = $this->getFields();

        return [
            'items'     => $items,
            'hideTitle' => !empty($this->data['hideTitle']),
            // On mobile, sidebars stack below the main content, pushing this module to
            // the bottom of the page. When enabled, a duplicate of the buttons is moved
            // (via JS, see acc-buttons.js) to the top of #main-content on mobile, while
            // this module keeps its normal sidebar position on desktop.
            'automaticMobileInsertion' => array_key_exists('automatic_mobile_insertion', $fields)
                ? !empty($fields['automatic_mobile_insertion'])
                : true,
            'mobileId' => uniqid('acc-buttons-mobile-'),
        ];
    }

    public function template(): string
    {
        return 'acc-buttons.blade.php';
    }
}
