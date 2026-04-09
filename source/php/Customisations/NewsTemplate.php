<?php

namespace PiteaCustomisation\Customisations;

/**
 * Injects view data for single nyhet templates (metadata above article).
 */
class NewsTemplate
{
    private const POST_TYPE = 'nyhet';

    private const TAXONOMY_NYHETSKATEGORI = 'nyhetskategori';

    public function __construct()
    {
        add_filter(
            'Municipio/Template/' . self::POST_TYPE . '/single/viewData',
            [$this, 'filterSingleViewData'],
            10,
            1
        );
    }

    /**
     * Add nyhetskategori tags for the single template.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterSingleViewData(array $data): array
    {
        $post = $data['post'] ?? null;
        if (!$post) {
            return $data;
        }

        $postId = 0;
        if (is_object($post) && method_exists($post, 'getId')) {
            $postId = (int) $post->getId();
        } elseif (is_object($post) && isset($post->id)) {
            $postId = (int) $post->id;
        }

        if ($postId < 1) {
            return $data;
        }

        $wpPost = get_post($postId);
        if (!$wpPost || $wpPost->post_type !== self::POST_TYPE) {
            return $data;
        }

        $terms = get_the_terms($postId, self::TAXONOMY_NYHETSKATEGORI);
        $newsTags = [];
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $newsTags[] = [
                    'label' => $term->name,
                ];
            }
        }

        $data['newsSingleTags'] = $newsTags;

        return $data;
    }
}
