<?php

namespace PiteaCustomisation\Customisations;

class Accessibility
{
    const READSPEAKER_CUSTOMER_ID = '9687';
    const READSPEAKER_READ_ID = 'article';
    const READSPEAKER_BASE_URL = 'https://app-eu.readspeaker.com/cgi-bin/rsent?customerid=%s&lang=sv_se&readid=%s&url=';

    const DEFAULT_BUTTON_STYLE = 'filled';
    const DEFAULT_BUTTON_COLOR = 'primary';

    public function __construct()
    {
        add_filter('Municipio/Template/single/viewData', [$this, 'addAccessibilityMenuToViewData']);
    }

    public function addAccessibilityMenuToViewData(array $data): array
    {
        $accessibilityMenuItem = $this->getReadSpeakerMenuItem();
        
        $data['accessibilityMenu']['items']['readspeaker'] = $accessibilityMenuItem;
        $data['accessibilityMenu']['items'] = $this->sortMenuItems($data['accessibilityMenu']['items']);
        $data['accessibilityMenu']['items'] = $this->changeDefaultStyles($data['accessibilityMenu']['items']);

        return $data;
    }

    private function changeDefaultStyles(array $items): array
    {
        foreach ($items as $key => $item) {
            if (!isset($item['style'])) {
                $items[$key]['style'] = self::DEFAULT_BUTTON_STYLE;
            }
            if (!isset($item['color'])) {
                $items[$key]['color'] = self::DEFAULT_BUTTON_COLOR;
            }
        }
        return $items;
    }

    private function sortMenuItems(array $items): array
    {
        $sortOrder = ['readspeaker', 'print'];

        $ordered = [];
        foreach ($sortOrder as $key) {
            if (isset($items[$key])) {
                $ordered[$key] = $items[$key];
                unset($items[$key]);
            }
        }
        $ordered += $items;

        return $ordered;
    }

    private function getReadSpeakerMenuItem(): array
    {
        $currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $readspeakerUrl = sprintf(self::READSPEAKER_BASE_URL, self::READSPEAKER_CUSTOMER_ID, self::READSPEAKER_READ_ID) . urlencode($currentUrl);
        
        return [
            'icon' => 'fa-solid fa-headphones',
            'href' => $readspeakerUrl,
            'text' => __('Listen', 'pitea-customisation'),
            'label' => __('Listen to this page', 'pitea-customisation'),
            'style' => self::DEFAULT_BUTTON_STYLE,
            'color' => self::DEFAULT_BUTTON_COLOR,
        ];
    }
}
