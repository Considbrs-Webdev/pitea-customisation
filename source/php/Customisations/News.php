<?php

namespace PiteaCustomisation\Customisations;

class News
{
    private const POST_TYPE = 'nyhet';

    private const TAXONOMY_NYHETSKATEGORI = 'nyhetskategori';

    public function __construct()
    {
        // Remove image from news item when used in posts module
        add_filter('ComponentLibrary/Component/NewsItem/Data', [$this, 'removeImageFromNewsItem']);
        add_filter('ComponentLibrary/Component/Tags/Data', [$this, 'removeTagLabel']);

        add_filter('rest_pre_insert_' . self::POST_TYPE, [$this, 'requireNyhetskategoriForRestSave'], 10, 2);
    }

    /**
     * Remove image from news item when used in posts module
     *
     * @param array $data
     * @return array
     */
    public function removeImageFromNewsItem(array $data): array
    {  
        $contexts = [
            'archive.list.news-item',
            'module.posts.news-item',
        ];

        $dataContexts = (array) ($data['context'] ?? []);
        if (!empty(array_intersect($contexts, $dataContexts))) {
            $data['image'] = null;
            $data['hasPlaceholderImage'] = false;
        }

        return $data;
    }

    /**
     * Remove tag label from tags component
     *
     * @param array $data
     * @return array
     */
    public function removeTagLabel(array $data): array
    {
        $data['beforeLabel'] = '';

        return $data;
    }

    /**
     * Block editor / REST: require at least one nyhetskategori when saving as publish, pending, or scheduled.
     *
     * @param \stdClass|\WP_Error $prepared_post
     * @return \stdClass|\WP_Error
     */
    public function requireNyhetskategoriForRestSave($prepared_post, \WP_REST_Request $request)
    {
        if (!$this->shouldEnforceNyhetskategori()) {
            return $prepared_post;
        }
        if (is_wp_error($prepared_post)) {
            return $prepared_post;
        }

        $status = $this->resolveIntendedPostStatus($prepared_post, $request);
        if (!$this->isPublicFacingStatus($status)) {
            return $prepared_post;
        }

        if ($this->willHaveNyhetskategoriAfterRestRequest($prepared_post, $request)) {
            return $prepared_post;
        }

        return new \WP_Error(
            'nyhetskategori_required',
            esc_html__('Du måste välja minst en nyhetskategori innan du publicerar.', 'pitea-customisation'),
            ['status' => 400]
        );
    }

    private function shouldEnforceNyhetskategori(): bool
    {
        if (defined('WP_IMPORTING') && WP_IMPORTING) {
            return false;
        }

        return true;
    }

    private function resolveIntendedPostStatus(object $prepared_post, \WP_REST_Request $request): string
    {
        if ($request->has_param('status')) {
            return (string) $request->get_param('status');
        }
        if (!empty($prepared_post->post_status)) {
            return (string) $prepared_post->post_status;
        }
        if (!empty($prepared_post->ID)) {
            $status = get_post_status((int) $prepared_post->ID);

            return $status ?: 'draft';
        }

        return 'draft';
    }

    private function isPublicFacingStatus(string $status): bool
    {
        return in_array($status, ['publish', 'pending', 'future'], true);
    }

    /**
     * After REST applies the request, will the post have at least one nyhetskategori term?
     */
    private function willHaveNyhetskategoriAfterRestRequest(object $prepared_post, \WP_REST_Request $request): bool
    {
        if ($request->has_param(self::TAXONOMY_NYHETSKATEGORI)) {
            $raw = $request->get_param(self::TAXONOMY_NYHETSKATEGORI);
            if (!is_array($raw)) {
                return false;
            }
            foreach ($raw as $id) {
                if ((int) $id > 0) {
                    return true;
                }
            }

            return false;
        }

        if (!empty($prepared_post->ID)) {
            return $this->postHasNyhetskategori((int) $prepared_post->ID);
        }

        return false;
    }

    private function postHasNyhetskategori(int $post_id): bool
    {
        $terms = wp_get_object_terms($post_id, self::TAXONOMY_NYHETSKATEGORI, ['fields' => 'ids']);

        return !is_wp_error($terms) && count($terms) > 0;
    }
}
