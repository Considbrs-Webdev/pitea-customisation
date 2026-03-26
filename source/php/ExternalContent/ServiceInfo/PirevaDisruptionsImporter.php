<?php

declare(strict_types=1);

namespace PiteaCustomisation\ExternalContent\ServiceInfo;

use ModularityServiceInfo\Import\ImporterInterface;
use ModularityServiceInfo\Import\ServiceInfoItem;
use ModularityServiceInfo\PostType\ServiceInformation;

/**
 * Imports disruption data from Pireva's REST API
 * and converts it to service information items.
 *
 * Items that disappear from the API response are automatically
 * marked for unpublishing (trashed) since Pireva doesn't provide
 * explicit end dates.
 *
 * @package PiteaCustomisation\ExternalContent
 */
class PirevaDisruptionsImporter implements ImporterInterface
{
    private const SOURCE_URL = 'https://www.pireva.se/wp-json/driftinformation/v1/items';
    private const META_SOURCE_KEY = '_service_info_import_source';

    /**
     * @return string
     */
    public function getKey(): string
    {
        return 'pireva-disruptions';
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Pireva Driftstörningar';
    }

    /**
     * @return ServiceInfoItem[]
     */
    public function import(): array
    {
        $data = $this->fetchData();

        $activeSourceIds = $this->extractSourceIds($data);
        $items           = [];

        foreach ($data as $disruption) {
            $item = $this->pirevaDisruptionToItem($disruption);

            if ($item !== null) {
                $items[] = $item;
            }
        }


        $expiredItems = $this->getItemsToUnpublish($activeSourceIds);
        $items        = array_merge($items, $expiredItems);

        return $items;
    }


    /**
     * Extract all source IDs from the API response.
     *
     * @param array $data
     * @return string[]
     */
    private function extractSourceIds(array $data): array
    {
        $ids = [];

        foreach ($data as $disruption) {
            $id = $disruption['id'] ?? null;
            if ($id !== null) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /**
     * Find existing posts that are no longer in the API response and create
     * ServiceInfoItems to trigger their unpublishing.
     *
     * @param string[] $activeSourceIds
     * @return ServiceInfoItem[]
     */
    private function getItemsToUnpublish(array $activeSourceIds): array
    {
        $existingPosts = $this->getExistingPirevaPostsMap();

        if (empty($existingPosts)) {
            return [];
        }

        $items = [];
        $now   = new \DateTimeImmutable('now', wp_timezone());

        foreach ($existingPosts as $sourceId => $post) {
            $sourceId = (string) $sourceId;

            if (in_array($sourceId, $activeSourceIds, true)) {
                continue;
            }

            $startDate = get_field('start_date', $post->ID);
            $startDt   = $startDate
                ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDate, wp_timezone())
                : $now;

            $items[] = new ServiceInfoItem(
                sourceId: $sourceId,
                title: $post->post_title,
                content: $post->post_content,
                startDate: $startDt ?: $now,
                endDate: $now,
                categories: [],
                unpublishDate: $now,
                onUnpublish: 'trash',
                publishDate: null,
            );
        }

        return $items;
    }

    /**
     * Query existing Pireva posts and return a map of sourceId => WP_Post.
     *
     * @return array<string, \WP_Post>
     */
    private function getExistingPirevaPostsMap(): array
    {
        $query = new \WP_Query([
            'post_type'              => ServiceInformation::POST_TYPE_NAME,
            'post_status'            => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page'         => -1,
            'meta_query'             => [
                [
                    'key'     => self::META_SOURCE_KEY,
                    'value'   => $this->getKey() . ':',
                    'compare' => 'LIKE',
                ],
            ],
            'no_found_rows'          => true,
            'suppress_filters'       => true,
            'update_post_term_cache' => false,
        ]);

        $map = [];

        foreach ($query->posts as $post) {
            $metaValue = get_post_meta($post->ID, self::META_SOURCE_KEY, true);
            $sourceId  = str_replace($this->getKey() . ':', '', $metaValue);
            $map[$sourceId] = $post;
        }

        return $map;
    }

    /**
     * Map a single disruption to a ServiceInfoItem.
     *
     * @param array $disruption
     * @return ServiceInfoItem|null
     */
    private function pirevaDisruptionToItem(array $disruption): ?ServiceInfoItem
    {
        $id    = $disruption['id'] ?? null;
        $title = $disruption['title'] ?? null;

        if ($id === null || empty($title)) {
            return null;
        }

        $publishDate = $this->parseDateTime($disruption['date'] ?? null);

        if ($publishDate === null) {
            return null;
        }

        $startDate = $this->parseStartDate($disruption['start_date'] ?? null);
        $endDate   = $this->parseStartDate($disruption['expected_end_date'] ?? null);
        $content   = $this->buildContent($disruption);


        $categories = $this->extractCategories($disruption['categories'] ?? []);

        $status = $disruption['status'] ?? null;
        if ($status === 'planned') {
            $categories[] = 'Planerade arbeten';
        }

        $serviceItem = new ServiceInfoItem(
            sourceId: (string) $id,
            title: $title,
            content: $content,
            startDate: $startDate ?? $publishDate,
            endDate: $endDate,
            categories: $categories,
            publishDate: $publishDate,
        );

        return $serviceItem;
    }

    /**
     * Build the post content from the disruption data.
     *
     * @param array $disruption
     * @return string
     */
    private function buildContent(array $disruption): string
    {
        $content = '';

        $description = trim($disruption['description'] ?? '');
        if (!empty($description)) {
            $content .= '<p><strong>' . esc_html($description) . '</strong></p>';
        }

        $mainContent = trim($disruption['content'] ?? '');
        if (!empty($mainContent)) {
            $content .= wp_kses_post($mainContent);
        }

        $statusUpdates = $disruption['status_updates'] ?? [];
        if (!empty($statusUpdates)) {
            $content .= '<ul>';
            foreach ($statusUpdates as $update) {
                $updateText = trim($update['update'] ?? '');
                $timestamp  = trim($update['timestamp'] ?? '');
                if (!empty($updateText)) {
                    $content .= '<li>';
                    if (!empty($timestamp)) {
                        $content .= '<strong>' . esc_html($timestamp) . '</strong>: ';
                    }
                    $content .= esc_html($updateText) . '</li>';
                }
            }
            $content .= '</ul>';
        }

        $link = $disruption['link'] ?? '';
        if (!empty($link)) {
            $content .= '<p><a href="' . esc_url($link) . '">' . __('Läs mer på pireva.se', 'pitea-customisation') . '</a></p>';
        }

        return $content;
    }

    /**
     * Parse a datetime string (format: "YYYY-MM-DD HH:MM:SS") into DateTimeImmutable.
     *
     * @param string|null $dateString
     * @return \DateTimeImmutable|null
     */
    private function parseDateTime(?string $dateString): ?\DateTimeImmutable
    {
        if (empty($dateString)) {
            return null;
        }

        $tz = wp_timezone();
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateString, $tz);

        return $dt !== false ? $dt : null;
    }

    /**
     * Parse a start/end date string (format: "YYYY-MM-DD - HH:MM") into DateTimeImmutable.
     *
     * @param string|null $dateString
     * @return \DateTimeImmutable|null
     */
    private function parseStartDate(?string $dateString): ?\DateTimeImmutable
    {
        if (empty($dateString)) {
            return null;
        }

        $tz = wp_timezone();
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d - H:i', $dateString, $tz);

        return $dt !== false ? $dt : null;
    }

    /**
     * Extract category names from the categories array.
     *
     * @param array $categories
     * @return string[]
     */
    private function extractCategories(array $categories): array
    {
        if (empty($categories)) {
            return [];
        }

        $names = array_map(fn($cat) => $cat['name'] ?? '', $categories);
        $names = array_filter($names);

        return array_values($names);
    }

    /**
     * Fetch disruption data from the Pireva API.
     *
     * @return array
     * @throws \RuntimeException On HTTP or parse errors
     */
    private function fetchData(): array
    {
        $response = wp_remote_get(self::SOURCE_URL, [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException(
                sprintf('Failed to fetch Pireva disruptions: %s', $response->get_error_message())
            );
        }

        // API returns 404 if no disruptions are found, dont wanna throw here.
        $statusCode = wp_remote_retrieve_response_code($response);
        $body       = wp_remote_retrieve_body($response);
        $data       = json_decode($body, true);

        if (is_array($data) && ($data['code'] ?? null) === 'no_driftinformation') {
            return [];
        }

        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf('Pireva disruptions endpoint returned HTTP %d', $statusCode)
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Failed to parse Pireva disruptions data.');
        }

        return $data;
    }
}
