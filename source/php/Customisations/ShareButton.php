<?php

namespace PiteaCustomisation\Customisations;

class ShareButton
{
    public function __construct()
    {
        new \PiteaCustomisation\AcfFields\ShareButtonFields();
        add_filter('the_content', [$this, 'addShareButton'], 10, 1);
    }

    public function addShareButton($content): string
    {
        if (!is_singular() || !is_main_query() || is_admin()) {
            return $content;
        }
        $placement = $this->getShareButtonPlacement();
        if ($placement === 'bottom') {
            return $content . $this->getShareButton();
        }

        return $content;
    }

    /**
     * Get the share button placement setting for the current page. If post type is not page, return 'bottom'.
     *
     * @return string The placement value: 'bottom' or 'none'
     */
    private function getShareButtonPlacement(): string
    {
        $postId = get_the_ID();
        if (!$postId) {
            return 'none';
        }

        $postType = get_post_type($postId);

        $placement = get_field('share_button_placement', $postId);

        if ($postType !== 'page') {
            return 'bottom';
        }

        if ($postType === 'page') {
            if (empty($placement)) {
                return 'none';
            }
            if ($placement === 'bottom') {
                return 'bottom';
            }
            return 'none';
        }

        return 'none';
    }

    private function getShareButton(): string
    {
        $url = esc_url(get_permalink());
        $title = esc_attr(get_the_title());

        return sprintf(
            '<div class="share-buttons">
                <button 
                    type="button"
                    class="share-link" 
                    data-js-share-button
                    data-share-url="%s"
                    aria-label="%s"
                >
                    <span class="share-link__text">%s</span>
                    <i class="fas fa-arrow-right share-link__icon" aria-hidden="true"></i>
                </button>
                <div class="share-popup-announcer u-sr__only" aria-live="polite" aria-atomic="true"></div>
            </div>',
            $url,
            esc_attr__('Copy page link to clipboard', 'pitea-customisation'),
            esc_html__('Dela sidan', 'pitea-customisation')
        );
    }
}
