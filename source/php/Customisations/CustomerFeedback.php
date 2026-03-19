<?php

namespace PiteaCustomisation\Customisations;

class CustomerFeedback
{
    private string $metaKey = '_customer_feedback_exclude';

    public function __construct()
    {
        add_filter('CustomerFeedback/post_types', array($this, 'hideCustomerFeedbackOnStartpage'));
        add_filter('CustomerFeedback/post_types', array($this, 'hideCustomerFeedbackOnExcludedPost'));
        add_action('add_meta_boxes', array($this, 'registerMetaBox'), 10, 2);
        add_action('save_post', array($this, 'saveMetaBox'));
        add_action('add_meta_boxes', array($this, 'removeSummaryMetaBox'), 1000);
    }

    /**
     * The form field name used for the exclude checkbox.
     */
    private function getFieldName(): string
    {
        return 'customer_feedback_exclude';
    }

    /**
     * The nonce field name used for the exclude checkbox.
     */
    private function getNonceName(): string
    {
        return $this->getFieldName() . '_nonce';
    }

    /**
     * Hide the customer feedback form on the start page.
     *
     * @param array|null $postTypes
     * @return array|null
     */
    public function hideCustomerFeedbackOnStartpage($postTypes)
    {
        if (!is_admin() && is_front_page()) {
            return [];
        }

        if (is_admin() && isset($_GET['post']) && (int) $_GET['post'] === (int) get_option('page_on_front')) {
            return [];
        }

        return $postTypes;
    }

    /**
     * Hide the customer feedback form on posts where it has been individually excluded.
     *
     * @param array|null $postTypes
     * @return array|null
     */
    public function hideCustomerFeedbackOnExcludedPost($postTypes)
    {
        global $post;

        if (is_a($post, 'WP_Post') && get_post_meta($post->ID, $this->metaKey, true)) {
            return [];
        }

        return $postTypes;
    }

    /**
     * Remove the feedback summary metabox from the front page edit screen, or
     * from any post that has the exclude meta set. Uses the current post's
     * post type when removing the metabox.
     */
    public function removeSummaryMetaBox(): void
    {
        if (!isset($_GET['post'])) {
            return;
        }

        $postId = (int) $_GET['post'];
        $post = get_post($postId);

        if (!$post) {
            return;
        }

        $shouldRemove = $postId === (int) get_option('page_on_front');

        if (!$shouldRemove) {
            $shouldRemove = (bool) get_post_meta($postId, $this->metaKey, true);
        }

        if ($shouldRemove) {
            remove_meta_box(
                'customer-feedback-summary-meta',
                $post->post_type,
                'side'
            );
        }
    }

    /**
     * Register a metabox on all post types where customer feedback is enabled.
     * Also registers on the specific post set as the front page, as an exception,
     * since the feedback form is always hidden there regardless of post type settings.
     * Because this hooks into add_meta_boxes (which fires per edit-screen), registering
     * the metabox for $postType here only affects the post currently being edited.
     *
     * @param string   $postType
     * @param \WP_Post|null $post
     */
    public function registerMetaBox(string $postType, ?\WP_Post $post): void
    {
        $allowedPostTypes = function_exists('get_field') ? get_field('customer_feedback_posttypes', 'option') : [];

        if (empty($allowedPostTypes) || !is_array($allowedPostTypes)) {
            $allowedPostTypes = [];
        }

        $isAllowedPostType = in_array($postType, $allowedPostTypes, true);

        if (!$isAllowedPostType) {
            return;
        }

        // When editing an existing post, skip if it is the front page.
        if ($post && $post->ID === (int) get_option('page_on_front')) {
            return;
        }

        add_meta_box(
            'customer-feedback-exclude',
            __('Customer Feedback', 'pitea-customisation'),
            array($this, 'renderMetaBox'),
            $postType,
            'side',
            'default'
        );
    }

    /**
     * Render the metabox content.
     *
     * @param \WP_Post $post
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field($this->getNonceName(), $this->getNonceName());
        $excluded = get_post_meta($post->ID, $this->metaKey, true);
?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->getFieldName()); ?>" value="1" <?php checked($excluded, '1'); ?>>
            <?php esc_html_e('Exclude customer feedback form on this page', 'pitea-customisation'); ?>
        </label>
<?php
    }

    /**
     * Save the metabox checkbox value.
     *
     * @param int $postId
     */
    public function saveMetaBox(int $postId): void
    {
        if (
            !isset($_POST[$this->getNonceName()]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->getNonceName()])), $this->getNonceName())
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if (!empty($_POST[$this->getFieldName()])) {
            update_post_meta($postId, $this->metaKey, '1');
        } else {
            delete_post_meta($postId, $this->metaKey);
        }
    }
}
