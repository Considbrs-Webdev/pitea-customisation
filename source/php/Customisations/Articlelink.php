<?php

namespace PiteaCustomisation\Customisations;

class Articlelink
{
    public function __construct()
    {
        add_filter('the_content', [$this, 'addShareButton'], 10, 1);
    }

    public function addShareButton($content): string
    {
        if (is_single() && is_main_query() && !is_admin()) {
            return $content . $this->getShareButton();
        }
        return $content;
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
