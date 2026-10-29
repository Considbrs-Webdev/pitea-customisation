<?php

declare(strict_types=1);

namespace PiteaCustomisation\Cli;

use PiteaCustomisation\Content\PuffarHeadingSpacer;

/**
 * Remove heading spacers that double the gap above puff cards.
 *
 * Manual Input card grids have a 32px top margin since Municipio 7.32.6, and
 * link cards have a 16px grid margin. Pages created from the old Temasida
 * starter still store a spacer between the heading and those modules.
 *
 * The command leaves every other spacer in place. It does not write unless
 * --apply is passed.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Save the updated post content. Without this flag the command only reports matches.
 *
 * ## EXAMPLES
 *
 *     wp pitea fix-puffar-heading-spacer
 *     wp pitea fix-puffar-heading-spacer --apply
 */
class FixPuffarHeadingSpacerCommand
{
    /**
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        unset($args);

        $apply = isset($assocArgs['apply']);
        $posts = $this->candidatePosts();
        $matched = 0;
        $spacers = 0;

        foreach ($posts as $post) {
            $result = PuffarHeadingSpacer::strip((string) $post->post_content);
            if ($result['removed'] === 0 || $result['content'] === $post->post_content) {
                continue;
            }

            $matched++;
            $spacers += $result['removed'];
            $detail = $result['details'] === [] ? '' : ' (' . implode('; ', $result['details']) . ')';
            \WP_CLI::log(sprintf(
                '#%d %s (%s, %s): %d spacer%s%s',
                (int) $post->ID,
                $post->post_title,
                $post->post_type,
                $post->post_status,
                $result['removed'],
                $result['removed'] === 1 ? '' : 's',
                $detail
            ));

            if (!$apply) {
                continue;
            }

            $this->save((int) $post->ID, $result['content']);
        }

        if ($matched === 0) {
            \WP_CLI::success('No heading spacers to remove.');
            return;
        }

        $verb = $apply ? 'Updated' : 'Would update';
        \WP_CLI::success(sprintf(
            '%s %d %s, %d %s.',
            $verb,
            $matched,
            $matched === 1 ? 'post' : 'posts',
            $spacers,
            $spacers === 1 ? 'spacer' : 'spacers'
        ));

        if (!$apply) {
            \WP_CLI::log('Dry run. Pass --apply to save.');
        }
    }

    /**
     * @return list<\WP_Post>
     */
    private function candidatePosts(): array
    {
        global $wpdb;

        $likeSpacer = '%' . $wpdb->esc_like('acf/spacer') . '%';
        $likeUnderPattern = '%' . $wpdb->esc_like('core/block/1858') . '%';
        $likeUnderName = '%' . $wpdb->esc_like('Puffar/inkastare undersida') . '%';
        $likeIconPattern = '%' . $wpdb->esc_like('core/block/1872') . '%';
        $likeIconName = '%' . $wpdb->esc_like('Puffar/inkastare med ikon') . '%';

        $sql = $wpdb->prepare(
            "SELECT ID, post_title, post_type, post_status, post_content
            FROM {$wpdb->posts}
            WHERE post_type IN ('page', 'wp_block')
            AND post_status IN ('publish', 'private', 'draft', 'pending', 'future')
            AND post_content LIKE %s
            AND (
                post_content LIKE %s
                OR post_content LIKE %s
                OR post_content LIKE %s
                OR post_content LIKE %s
            )",
            $likeSpacer,
            $likeUnderPattern,
            $likeUnderName,
            $likeIconPattern,
            $likeIconName
        );

        $rows = $wpdb->get_results($sql);
        if (!is_array($rows)) {
            return [];
        }

        return $rows;
    }

    private function save(int $postId, string $content): void
    {
        kses_remove_filters();
        $updated = wp_update_post(wp_slash([
            'ID' => $postId,
            'post_content' => $content,
        ]), true);
        kses_init_filters();

        if (is_wp_error($updated) || $updated === 0) {
            $message = is_wp_error($updated) ? $updated->get_error_message() : 'wp_update_post returned 0';
            \WP_CLI::warning(sprintf('#%d was not saved: %s', $postId, $message));
        }
    }
}
