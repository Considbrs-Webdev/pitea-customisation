<?php

namespace PiteaCustomisation\Customisations;

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
 */
class Accessibility
{
    const READSPEAKER_CUSTOMER_ID = '9687';
    const READSPEAKER_READ_ID = 'article';
    const READSPEAKER_BASE_URL = 'https://app-eu.readspeaker.com/cgi-bin/rsent?customerid=%s&lang=sv_se&readid=%s&url=';

    const DEFAULT_BUTTON_STYLE = 'filled';
    const DEFAULT_BUTTON_COLOR = 'primary';

    public function __construct()
    {
        new \PiteaCustomisation\AcfFields\AccessibilityFields();
        add_filter('Municipio/Template/viewData', [$this, 'maybeAddPrintMenuToViewData'], 10, 1);
        add_filter('Municipio/Template/viewData', [$this, 'addAccessibilityMenuToViewData'], 20, 1);
        add_action('template_redirect', [$this, 'maybeWrapContentInArticle']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueWebReaderScript'], 10);
        add_filter('script_loader_tag', [$this, 'addReadSpeakerScriptId'], 10, 3);
    }

    /**
     * Conditionally registers the article wrapper filters.
     *
     * Called on template_redirect. If the current request is a singular page
     * (but not the front page) and the accessibility menu should be shown, hooks
     * openArticleWrapper() and closeArticleWrapper() so that the page modules
     * are wrapped in <article id="article">. ReadSpeaker uses this id to locate
     * the content it should read aloud.
     */
    public function maybeWrapContentInArticle(): void
    {
        if (!is_front_page() && is_singular() && $this->shouldShowAccessibilityMenu()) {
            add_filter('Municipio/Hook/innerLoopStart', [$this, 'openArticleWrapper']);
            add_filter('Municipio/Hook/innerLoopEnd', [$this, 'closeArticleWrapper']);
        }
    }

    /**
     * Returns the opening <article> tag that wraps the page content modules,
     * followed by a visually hidden ReadSpeaker button that webReader.js
     * initialises on. The hidden button is positioned at the top of the article
     * so that when triggered the player expands there (matching the in-page
     * preview layout). Our custom accessibility menu button triggers this hidden
     * button via JavaScript rather than navigating to the rsent URL directly.
     */
    public function openArticleWrapper(string $content): string
    {
        $currentUrl     = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $readspeakerUrl = sprintf(
            self::READSPEAKER_BASE_URL,
            self::READSPEAKER_CUSTOMER_ID,
            self::READSPEAKER_READ_ID
        ) . urlencode($currentUrl);

        return '<article id="article">'
            . '<div id="readspeaker-hidden-btn" class="rsbtn rs_skip" style="display:none;" aria-hidden="true">'
            . '<a rel="nofollow" class="rsbtn_play" href="' . esc_attr($readspeakerUrl) . '">'
            . '<span class="rsbtn_left rsimg rspart"><span class="rsbtn_text"><span>' . esc_html__('Listen to this page', 'pitea-customisation') . '</span></span></span>'
            . '<span class="rsbtn_right rsimg rsplay rspart"></span>'
            . '</a>'
            . '</div>';
    }

    /**
     * Returns the closing </article> tag that ends the ReadSpeaker content region.
     */
    public function closeArticleWrapper(string $content): string
    {
        return '</article>';
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

        $scriptUrl = 'https://cdn-eu.readspeaker.com/script/' . self::READSPEAKER_CUSTOMER_ID . '/webReader/webReader.js?pids=wr';
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
            return $data;
        }

        if (!$this->shouldShowAccessibilityMenu()) {
            return $data;
        }

        $accessibilityMenuItem = $this->getReadSpeakerMenuItem();

        $data['accessibilityMenu']['items']['readspeaker'] = $accessibilityMenuItem;
        $data['accessibilityMenu']['items'] = $this->sortMenuItems($data['accessibilityMenu']['items']);
        $data['accessibilityMenu']['items'] = $this->changeDefaultStyles($data['accessibilityMenu']['items']);

        return $data;
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
            'icon'   => 'fa-solid fa-headphones',
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
