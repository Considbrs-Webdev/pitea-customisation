<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Admin\Tabs\ReadSpeakerTab;

/**
 * Handles accessibility features for the site, including:
 *
 * - ReadSpeaker integration: Adds a "Listen to this page" button to singular
 *   pages and wraps the page content modules in <article id="article"> so that
 *   ReadSpeaker knows which portion of the page to read aloud.
 *
 * - Print button: Optionally adds a print button to the accessibility menu on
 *   singular pages.
 *
 * Whether the accessibility menu (and the article wrapper) is shown on a given
 * page can be controlled per-post via the ACF field "show_accessibility_buttons".
 * For non-page post types the menu is always shown.
 *
 * Optional: the Modularity module AccButtons repeats the same buttons in a sidebar.
 * When “Use module placement” is enabled in settings, nav-helper buttons are removed
 * from view data so only the module shows them.
 */
class Accessibility
{
    const READSPEAKER_BASE_URL = 'https://app-eu.readspeaker.com/cgi-bin/rsent?customerid=%s&lang=sv_se&readid=%s&url=';

    const DEFAULT_BUTTON_STYLE = 'filled';
    const DEFAULT_BUTTON_COLOR = 'primary';

    /**
     * Copy of merged accessibility menu items for the AccButtons module (after viewData priority 20).
     *
     * @var array<string, mixed>|null
     */
    private static ?array $accessibilityMenuItemsSnapshot = null;

    public function __construct()
    {
        new \PiteaCustomisation\AcfFields\AccessibilityFields();
        add_filter('Municipio/Template/viewData', [$this, 'maybeAddPrintMenuToViewData'], 10, 1);
        add_filter('Municipio/Template/viewData', [$this, 'addAccessibilityMenuToViewData'], 20, 1);
        add_filter('Municipio/Template/viewData', [$this, 'stripNavAccessibilityMenuWhenUsingModule'], 30, 1);
        add_action('Municipio/Hook/innerLoopStart', [$this, 'addReadSpeakerHiddenButton']);
        add_action('template_redirect', [$this, 'maybeWrapContentInArticle']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueWebReaderScript'], 10);
        add_filter('script_loader_tag', [$this, 'addReadSpeakerScriptId'], 10, 3);
    }

    public function addReadSpeakerHiddenButton($content): string
    {
        if (!is_singular() || is_front_page() || !$this->shouldShowAccessibilityMenu()) {
            return $content;
        }

        $customerId = ReadSpeakerTab::getCustomerId();
        $readId = ReadSpeakerTab::getReadId();

        $currentUrl     = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $readspeakerUrl = sprintf(
            self::READSPEAKER_BASE_URL,
            $customerId,
            $readId
        ) . urlencode($currentUrl);

        return $content . '<div id="readspeaker-hidden-btn" class="rsbtn rs_skip" style="display:none;" aria-hidden="true">'
            . '<a rel="nofollow" class="rsbtn_play" href="' . esc_attr($readspeakerUrl) . '">'
            . '<span class="rsbtn_left rsimg rspart"><span class="rsbtn_text"><span>' . esc_html__('Listen to this page', 'pitea-customisation') . '</span></span></span>'
            . '<span class="rsbtn_right rsimg rsplay rspart"></span>'
            . '</a>'
            . '</div>';
    }

    /**
     * Conditionally registers the article wrapper filters.
     *
     * Called on template_redirect. If the current request is a singular page
     * (but not the front page) and the accessibility menu should be shown, hooks
     * openArticleWrapper() and closeArticleWrapper() so that the page modules
     * are wrapped in <article id="article">. ReadSpeaker uses this id to locate
     * the content it should read aloud. The article wrapper is only added when
     * using the 'one-page.blade.php' template to avoid conflicts with existing
     * article tags in other templates.
     */
    public function maybeWrapContentInArticle(): void
    {
        if (!is_front_page() && is_singular() && $this->shouldShowAccessibilityMenu()) {
            $template = get_page_template_slug();
            if ($template === 'one-page.blade.php') {
                // Get read ID from settings
                $readId = ReadSpeakerTab::getReadId();

                add_filter('Municipio/Hook/innerLoopStart', [$this, 'openArticleWrapper'], 20);
                add_filter('Municipio/Hook/innerLoopEnd', [$this, 'closeArticleWrapper'], 20);
            }
        }
    }

    public function openArticleWrapper($content): string
    {
        $readId = ReadSpeakerTab::getReadId();

        return $content . '<article id="' . esc_attr($readId) . '">';
    }

    public function closeArticleWrapper($content): string
    {
        return $content . '</article>';
    }

    /**
     * Enqueues the ReadSpeaker webReader script on singular pages where the
     * accessibility menu is shown. When loaded, webReader intercepts clicks on
     * the Listen link and opens the player in-page with highlighting instead
     * of navigating away.
     */
    public function enqueueWebReaderScript(): void
    {
        if (!is_singular() || is_front_page() || !$this->shouldShowAccessibilityMenu()) {
            return;
        }

        $customerId = ReadSpeakerTab::getCustomerId();

        $scriptUrl = 'https://cdn-eu.readspeaker.com/script/' . $customerId . '/webReader/webReader.js?pids=wr';
        wp_enqueue_script(
            'readspeaker-webreader',
            $scriptUrl,
            [],
            null,
            false
        );

        wp_add_inline_script(
            'readspeaker-webreader',
            "window.rsConf = { settings: { hl: 'wordsent', hlscroll: true } };",
            'before'
        );
    }

    /**
     * Adds the required id="rs_req_Init" to the ReadSpeaker webReader script tag.
     * ReadSpeaker requires this id for correct loading.
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @param string $src    The script source URL.
     * @return string Modified script tag.
     */
    public function addReadSpeakerScriptId(string $tag, string $handle, string $src): string
    {
        if ($handle !== 'readspeaker-webreader') {
            return $tag;
        }

        $tag = preg_replace('/\sid=[\'"][^\'"]*[\'"]/', '', $tag);

        return str_replace('<script ', '<script id="rs_req_Init" ', $tag);
    }

    public function maybeAddPrintMenuToViewData(array $data): array
    {
        if (!is_singular()) {
            return $data;
        }

        if (!$this->shouldShowAccessibilityMenu()) {
            return $data;
        }

        if (!isset($data['accessibilityMenu']['print'])) {
            $data['accessibilityMenu']['items']['print'] = $this->getPrintMenuItem();
        }

        return $data;
    }

    public function addAccessibilityMenuToViewData(array $data): array
    {
        if (!is_singular()) {
            self::$accessibilityMenuItemsSnapshot = null;

            return $data;
        }

        if (!$this->shouldShowAccessibilityMenu()) {
            self::$accessibilityMenuItemsSnapshot = null;

            return $data;
        }

        $accessibilityMenuItem = $this->getReadSpeakerMenuItem();

        $data['accessibilityMenu']['items']['readspeaker'] = $accessibilityMenuItem;
        $data['accessibilityMenu']['items'] = $this->sortMenuItems($data['accessibilityMenu']['items']);
        $data['accessibilityMenu']['items'] = $this->changeDefaultStyles($data['accessibilityMenu']['items']);

        self::$accessibilityMenuItemsSnapshot = $data['accessibilityMenu']['items'];

        return $data;
    }

    /**
     * When module placement is enabled, remove items from the nav-helper accessibility
     * partial so only the AccButtons module shows Listen/Print.
     */
    public function stripNavAccessibilityMenuWhenUsingModule(array $data): array
    {
        if (!is_singular() || !$this->shouldShowAccessibilityMenu()) {
            return $data;
        }

        if (!ReadSpeakerTab::useModulePlacementForAccessibility()) {
            return $data;
        }

        if (!isset($data['accessibilityMenu']) || !is_array($data['accessibilityMenu'])) {
            return $data;
        }

        $data['accessibilityMenu']['items'] = [];

        return $data;
    }

    /**
     * Final merged items for the AccButtons Modularity module (same as nav bar when not stripped).
     *
     * @return array<string, mixed>
     */
    public static function getAccessibilityMenuItemsSnapshot(): array
    {
        return self::$accessibilityMenuItemsSnapshot ?? [];
    }

    /**
     * Determine if accessibility menu should be shown
     * 
     * @return bool True if menu should be shown, false otherwise
     */
    private function shouldShowAccessibilityMenu(): bool
    {
        if (!is_singular()) {
            return false;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return false;
        }

        $postType = get_post_type($postId);

        if ($postType === 'page') {
            $showButtons = get_field('show_accessibility_buttons', $postId);
            if ($showButtons === null || $showButtons === false) {
                return false;
            }
            return (bool) $showButtons;
        }

        return true;
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
        return [
            'icon'   => 'fa-solid fa-ear',
            'href'   => '#',
            'script' => 'var btn=document.querySelector("#readspeaker-hidden-btn .rsbtn_play");if(btn){btn.click();}return false;',
            'text'   => __('Listen to this page', 'pitea-customisation'),
            'label'  => __('Listen to this page with ReadSpeaker', 'pitea-customisation'),
            'style'  => self::DEFAULT_BUTTON_STYLE,
            'color'  => self::DEFAULT_BUTTON_COLOR,
        ];
    }

    private function getPrintMenuItem(): array
    {
        return [
            'icon' => 'print',
            'href' => '#',
            'script' => 'window.print();return false;',
            'text' => __('Print', 'municipio'),
            'label' => __('Print this page', 'municipio'),
        ];
    }
}
