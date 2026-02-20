<?php

namespace PiteaCustomisation\Customisations;

class ShareButton
{
    public function __construct()
    {
        new \PiteaCustomisation\AcfFields\ShareButtonFields();
        add_action('signature_before', [$this, 'renderSignatureBefore']);
        add_action('signature_after', [$this, 'renderSignatureAfter']);
    }

    /**
     * Output wrapper opening before the signature (core hook).
     */
    public function renderSignatureBefore(): void
    {
        if ($this->shouldShowShareButton()) {
            echo '<div class="c-signature-row">';
        }
    }

    /**
     * Output share button and wrapper closing after the signature (core hook).
     */
    public function renderSignatureAfter(): void
    {
        if ($this->shouldShowShareButton()) {
            echo $this->getShareButtonHtml();
            echo '</div>';
        }
    }

    private function shouldShowShareButton(): bool
    {
        if (!is_singular() || is_admin()) {
            return false;
        }

        return $this->getShareButtonPlacement() === 'bottom';
    }

    private function getShareButtonPlacement(): string
    {
        $postId = get_the_ID();
        if (!$postId) {
            return 'none';
        }

        $postType = get_post_type($postId);

        if ($postType !== 'page') {
            return 'bottom';
        }

        $placement = get_field('share_button_placement', $postId);

        return ($placement === 'bottom') ? 'bottom' : 'none';
    }

    private function getShareButtonHtml(): string
    {
        $url = esc_url(get_permalink());

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
            esc_html__('Share page', 'pitea-customisation')
        );
    }
}
