<?php

namespace PiteaCustomisation\Customisations;

/**
 * Fills Posts module taxonomy selects in the ACF expanded editor.
 *
 * @upstream-shim id=posts-taxonomy-filter-expanded-editor
 * @upstream-repo municipio (Modularity Posts blockFilteringSetup.ts)
 * @upstream-broken BlockFilteringSetup fills taxonomy selects once in the block sidebar. ACF block v3 expanded editor remounts the form from HTML whose choices are empty, and the script does not run again.
 * @upstream-fix-needed Populate posts_taxonomy_type and posts_taxonomy_value when the block form is rendered.
 * @remove-when Municipio Posts block form includes those choices in the expanded editor. Installed 7.38.6 and 7.70.3 do not (blockFilteringSetup.ts is unchanged).
 * @verify-removal Edit Böle skola (post 8738), open the Inlägg block expanded editor: Taxonomi lists Nyhetskategorier and Term lists Böle skola without this class.
 */
class PostsTaxonomyFilterModal
{
    private const TAXONOMY_TYPE_KEY = 'field_571e048136f10';

    private const TAXONOMY_VALUE_KEY = 'field_571e049636f11';

    private const POST_TYPE_KEY = 'field_571dfc40f8114';

    /**
     * Register the block-form filters.
     */
    public function __construct()
    {
        add_filter(
            'acf/prepare_field/key=' . self::TAXONOMY_TYPE_KEY,
            [$this, 'prepareTaxonomyType']
        );
        add_filter(
            'acf/prepare_field/key=' . self::TAXONOMY_VALUE_KEY,
            [$this, 'prepareTaxonomyValue']
        );
    }

    /**
     * Add taxonomy choices for the block's post type.
     *
     * Runs while ACF renders the block form, after the block meta store is active.
     * The expanded editor remounts that HTML, so the choices have to be in the markup.
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function prepareTaxonomyType(array $field): array
    {
        $postType = $this->blockMetaValue('posts_data_post_type', self::POST_TYPE_KEY);
        $choices = $this->taxonomyChoices($postType);

        if ($choices !== []) {
            $field['choices'] = $choices;
        }

        return $field;
    }

    /**
     * Add term choices for the block's taxonomy, or the post type's first taxonomy.
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function prepareTaxonomyValue(array $field): array
    {
        $taxonomy = $this->blockMetaValue('posts_taxonomy_type', self::TAXONOMY_TYPE_KEY);

        if ($taxonomy === '') {
            $postType = $this->blockMetaValue('posts_data_post_type', self::POST_TYPE_KEY);
            $taxonomies = $this->taxonomyChoices($postType);
            $taxonomy = array_key_first($taxonomies) ?? '';
        }

        $choices = $this->termChoices($taxonomy);

        if ($choices !== []) {
            $field['choices'] = $choices;
        }

        return $field;
    }

    /**
     * Read one value from the block meta ACF set up for the current form render.
     *
     * @param string $name Field name.
     * @param string $key Field key.
     * @return string
     */
    private function blockMetaValue(string $name, string $key): string
    {
        if (!function_exists('acf_get_instance')) {
            return '';
        }

        $store = acf_get_instance('ACF_Local_Meta');
        if (!is_object($store)) {
            return '';
        }

        $postId = $store->post_id ?? 0;
        if (!is_string($postId) || !str_starts_with($postId, 'block_')) {
            return '';
        }

        $meta = $store->meta[$postId] ?? null;
        if (!is_array($meta)) {
            return '';
        }

        foreach ([$name, $key] as $candidate) {
            $value = $meta[$candidate] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Taxonomy name => label for a post type.
     *
     * @param string $postType
     * @return array<string, string>
     */
    private function taxonomyChoices(string $postType): array
    {
        if ($postType === '' || !post_type_exists($postType)) {
            return [];
        }

        $objects = get_object_taxonomies($postType, 'objects');
        if (!is_array($objects)) {
            return [];
        }

        $choices = [];
        foreach ($objects as $taxonomy) {
            if (!is_object($taxonomy) || !isset($taxonomy->name, $taxonomy->label)) {
                continue;
            }

            $choices[(string) $taxonomy->name] = (string) $taxonomy->label;
        }

        return $choices;
    }

    /**
     * Term slug => name for a taxonomy.
     *
     * @param string $taxonomy
     * @return array<string, string>
     */
    private function termChoices(string $taxonomy): array
    {
        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $choices = [];
        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $choices[$term->slug] = $term->name;
        }

        return $choices;
    }
}
