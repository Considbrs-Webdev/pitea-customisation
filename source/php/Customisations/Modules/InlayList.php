<?php

namespace PiteaCustomisation\Customisations\Modules;

/**
 * Inlay List module: icons, per-row "open in new tab" for external links, Select2 tweaks.
 */
class InlayList
{
    private const ITEMS_REPEATER_KEY = 'field_569e0559eb084';

    private const TYPE_FIELD_KEY = 'field_569e068b33f31';

    private const LINK_EXTERNAL_FIELD_KEY = 'field_569e06f633f32';

    private const OPEN_IN_NEW_TAB_FIELD_KEY = 'field_pitea_inlay_open_new_tab';

    private const OPEN_IN_NEW_TAB_FIELD_NAME = 'inlay_list_open_in_new_tab';

    public function __construct()
    {
        add_action('acf/init', [$this, 'registerFields'], 20);
        add_filter(
            'acf/load_field/key=' . self::ITEMS_REPEATER_KEY,
            [$this, 'orderOpenInNewTabFieldAfterLinkExternal'],
            99,
            1
        );
        add_filter('Modularity/Display/mod-inlaylist/viewData', [$this, 'modifyInlayListData'], 10, 1);
        add_action('acf/input/admin_footer', [$this, 'enqueueSelect2EscapeMarkupScript']);
    }

    public function registerFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        acf_add_local_field([
            'key' => self::OPEN_IN_NEW_TAB_FIELD_KEY,
            'label' => __('Open in new tab', 'pitea-customisation'),
            'name' => self::OPEN_IN_NEW_TAB_FIELD_NAME,
            'type' => 'true_false',
            'instructions' => __(
                'Opens the external link in a new browser tab when enabled.',
                'pitea-customisation'
            ),
            'required' => 0,
            'conditional_logic' => [
                [
                    [
                        'field' => self::TYPE_FIELD_KEY,
                        'operator' => '==',
                        'value' => 'external',
                    ],
                ],
            ],
            'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
            'message' => __('Enable', 'pitea-customisation'),
            'default_value' => 0,
            'ui' => 1,
            'ui_on_text' => '',
            'ui_off_text' => '',
            'parent' => self::ITEMS_REPEATER_KEY,
        ]);
    }

    /**
     * Place the toggle after the external URL field in the repeater row.
     *
     * @param array<string, mixed>|false $field
     * @return array<string, mixed>|false
     */
    public function orderOpenInNewTabFieldAfterLinkExternal($field)
    {
        if (!is_array($field) || empty($field['sub_fields']) || !is_array($field['sub_fields'])) {
            return $field;
        }

        $subFields = $field['sub_fields'];
        $openKey = self::OPEN_IN_NEW_TAB_FIELD_KEY;
        $afterKey = self::LINK_EXTERNAL_FIELD_KEY;
        $openIndex = null;
        $afterIndex = null;

        foreach ($subFields as $index => $subField) {
            if (!is_array($subField) || empty($subField['key'])) {
                continue;
            }
            if ($subField['key'] === $openKey) {
                $openIndex = $index;
            }
            if ($subField['key'] === $afterKey) {
                $afterIndex = $index;
            }
        }

        if ($openIndex === null || $afterIndex === null || $openIndex === $afterIndex + 1) {
            return $field;
        }

        $openField = $subFields[$openIndex];
        unset($subFields[$openIndex]);
        $subFields = array_values($subFields);

        $insertAt = $afterIndex + 1;
        if ($openIndex < $afterIndex) {
            $insertAt = $afterIndex;
        }

        array_splice($subFields, $insertAt, 0, [$openField]);
        $field['sub_fields'] = $subFields;

        return $field;
    }

    public function enqueueSelect2EscapeMarkupScript(): void
    {
?>
        <script type="text/javascript">
            (function($) {
                if (typeof acf !== 'undefined') {
                    acf.add_filter('select2_args', function(args, $el, settings, field, type) {
                        if (field.data('name') === 'link_internal') {
                            args.escapeMarkup = function(markup) {
                                return markup;
                            };
                        }
                        return args;
                    });
                }
            })(jQuery);
        </script>
<?php
    }

    /**
     * Add icons and optional new-tab attributes from per-row ACF data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function modifyInlayListData(array $data): array
    {
        if (empty($data['items']) || !is_array($data['items'])) {
            return $data;
        }

        $postId = $data['ID'] ?? null;
        $rawItems = is_numeric($postId)
            ? (get_field('items', (int) $postId) ?: [])
            : (get_field('items') ?: []);

        foreach ($data['items'] as $index => &$item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['icon'])) {
                if ($this->isPdfLink($item['href'] ?? '')) {
                    $item['icon'] = 'fa-solid fa-file-pdf';
                } elseif ($this->isExternalLink($item['href'] ?? '')) {
                    $item['icon'] = 'fa-solid fa-arrow-up-right-from-square';
                } else {
                    $item['icon'] = 'fa-solid fa-arrow-right';
                }
            }

            $rawRow = $rawItems[$index] ?? null;
            if (!is_array($rawRow)) {
                continue;
            }

            if (
                ($rawRow['type'] ?? '') === 'external'
                && !empty($rawRow[self::OPEN_IN_NEW_TAB_FIELD_NAME])
            ) {
                $item['attributeList'] = array_merge($item['attributeList'] ?? [], [
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                ]);
            }
        }
        unset($item);

        return $data;
    }

    /**
     * @param string $url
     */
    private function isExternalLink(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);

        if (!$urlHost) {
            return false;
        }

        $siteHost = parse_url(home_url(), PHP_URL_HOST);

        return $urlHost !== $siteHost;
    }

    /**
     * @param string $url
     */
    private function isPdfLink(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if ($path === false || $path === null) {
            return false;
        }

        return strtolower(substr($path, -4)) === '.pdf';
    }
}
