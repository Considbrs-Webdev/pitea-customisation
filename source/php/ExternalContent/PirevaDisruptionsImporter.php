<?php

declare(strict_types=1);

namespace PiteaCustomisation\ExternalContent;

use ModularityServiceInfo\Import\ImporterInterface;
use ModularityServiceInfo\Import\ServiceInfoItem;

/**
 * Imports disruption data from Pireva's REST API
 * and converts it to service information items.
 *
 * @package PiteaCustomisation\ExternalContent
 */
class PirevaDisruptionsImporter implements ImporterInterface
{
    private const SOURCE_URL = 'https://www.pireva.se/wp-json/driftinformation/v1/items';

    // public function __construct()
    // {
    //     $this->import();
    // }
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

        if (empty($data)) {
            return [];
        }

        $items = [];

        foreach ($data as $disruption) {
            $item = $this->pirevaDisruptionToItem($disruption);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
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

        return new ServiceInfoItem(
            sourceId: (string) $id,
            title: $title,
            content: $content,
            startDate: $startDate ?? $publishDate,
            endDate: $endDate,
            categories: $categories,
            publishDate: $publishDate,
        );
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
            return ['Pireva'];
        }

        $names = array_map(fn($cat) => $cat['name'] ?? '', $categories);
        $names = array_filter($names);

        return !empty($names) ? $names : ['Pireva'];
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

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf('Pireva disruptions endpoint returned HTTP %d', $statusCode)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Failed to parse Pireva disruptions data.');
        }

        return $data;
    }
}
