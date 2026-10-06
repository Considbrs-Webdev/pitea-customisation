<?php

namespace PiteaCustomisation\Customisations;

use WP_Block_Editor_Context;
use WP_Post;

/**
 * Ensures reusable patterns (wp_block) remain visible when block allow-lists are active.
 *
 * @upstream-shim id=municipio-block-pattern-allowlist
 * @upstream-repo municipio (block editor allow-list)
 * @upstream-broken Municipio restricts allowed_block_types; blocks inside published wp_block patterns may be treated as disallowed.
 * @upstream-fix-needed Include pattern dependency block types in the allow-list or expose a supported extension point.
 * @remove-when Page editor shows all intended reusable patterns without merging block types from wp_block posts.
 * @verify-removal Disable this class; confirm pattern inserter still lists Piteå patterns on a page edit screen.
 */
class BlockPatternCompatibility
{
    public function __construct()
    {
        add_filter('allowed_block_types_all', [$this, 'ensurePatternBlockTypesAreAllowed'], 20, 2);
    }

    /**
     * Add block types used by published wp_block patterns to the allowed list.
     *
     * This preserves Municipio's restricted editor while preventing valid patterns
     * from being hidden due to compatibility checks against allowed block types.
     *
     * @param bool|array<int,string> $allowedBlockTypes
     * @param WP_Block_Editor_Context $context
     * @return bool|array<int,string>
     */
    public function ensurePatternBlockTypesAreAllowed($allowedBlockTypes, $context)
    {
        if (!$this->isPageEditorContext($context)) {
            return $allowedBlockTypes;
        }

        if (!is_array($allowedBlockTypes)) {
            return $allowedBlockTypes;
        }

        $allowed = [];
        foreach ($allowedBlockTypes as $blockName) {
            if (is_string($blockName) && $blockName !== '') {
                $allowed[$blockName] = true;
            }
        }

        foreach ($this->getBlockTypesUsedInPublishedPatterns() as $requiredBlockType) {
            $allowed[$requiredBlockType] = true;
        }

        return array_keys($allowed);
    }

    private function isPageEditorContext($context): bool
    {
        if (!is_admin() || !($context instanceof WP_Block_Editor_Context)) {
            return false;
        }

        if (!isset($context->post) || !($context->post instanceof WP_Post)) {
            return false;
        }

        return $context->post->post_type === 'page';
    }

    /**
     * @return array<int,string>
     */
    private function getBlockTypesUsedInPublishedPatterns(): array
    {
        $posts = get_posts([
            'post_type' => 'wp_block',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $set = [];
        foreach ($posts as $postId) {
            $post = get_post((int) $postId);
            if (!$post instanceof WP_Post) {
                continue;
            }

            $this->collectBlockNamesFromTree(parse_blocks($post->post_content), $set);
        }

        $blockNames = array_keys($set);
        sort($blockNames);

        return $blockNames;
    }

    /**
     * @param array<int,array<string,mixed>> $blocks
     * @param array<string,bool> $set
     */
    private function collectBlockNamesFromTree(array $blocks, array &$set): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (!empty($block['blockName']) && is_string($block['blockName'])) {
                $set[$block['blockName']] = true;
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $this->collectBlockNamesFromTree($block['innerBlocks'], $set);
            }
        }
    }
}
