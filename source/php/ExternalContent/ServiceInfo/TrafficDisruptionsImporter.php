<?php

declare(strict_types=1);

namespace PiteaCustomisation\ExternalContent\ServiceInfo;

use ModularityServiceInfo\Import\ImporterInterface;
use ModularityServiceInfo\Import\ServiceInfoItem;

/**
 * Class TrafficDisruptionsImporter
 *
 * Imports traffic disruption data from Piteå's ArcGIS FeatureServer
 * and converts it to service information items with embedded maps.
 *
 * @package PiteaCustomisation\ExternalContent
 */
class TrafficDisruptionsImporter implements ImporterInterface
{
    /**
     * WordPress option name that stores the source URL for this importer.
     * Registered and managed via the plugin settings page.
     */
    public const OPTION_SOURCE_URL = 'pitea_customisation_traffic_disruptions_source_url';

    public function getKey(): string
    {
        return 'pitea-trafikstorningar';
    }

    public function getName(): string
    {
        return 'Piteå Trafikstörningar';
    }

    /**
     * @return ServiceInfoItem[]
     */
    public function import(): array
    {
        $sourceUrl = (string) get_option(self::OPTION_SOURCE_URL, '');

        if (empty($sourceUrl)) {
            return [];
        }

        $geoJson = $this->fetchGeoJson($sourceUrl);

        if (empty($geoJson['features'])) {
            return [];
        }

        $items = [];

        foreach ($geoJson['features'] as $feature) {
            $item = $this->mapFeatureToItem($feature);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Fetch the GeoJSON data from the ArcGIS endpoint.
     *
     * @param  string $sourceUrl The GeoJSON endpoint URL.
     * @return array             Decoded GeoJSON FeatureCollection
     * @throws \RuntimeException On HTTP or parse errors
     */
    private function fetchGeoJson(string $sourceUrl): array
    {
        $response = wp_remote_get($sourceUrl, [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException(
                sprintf('Failed to fetch GeoJSON: %s', $response->get_error_message())
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            throw new \RuntimeException(
                sprintf('GeoJSON endpoint returned HTTP %d', $statusCode)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Failed to parse GeoJSON response.');
        }

        return $data;
    }

    /**
     * Map a single GeoJSON feature to a ServiceInfoItem.
     */
    private function mapFeatureToItem(array $feature): ?ServiceInfoItem
    {
        $props = $feature['properties'] ?? [];

        $objectId = $props['OBJECTID'] ?? null;
        $title    = $props['RUBRIK'] ?? null;
        $info     = $props['INFO'] ?? '';

        if ($objectId === null || empty($title)) {
            return null;
        }

        $publishDate   = $this->convertTimestamp($props['PUBLICERA_START'] ?? null);
        $unpublishDate = $this->convertTimestamp($props['PUBLICERA_SLUT'] ?? null);

        if ($publishDate === null) {
            return null;
        }

        [$startDate, $endDate] = $this->parseTidField($props['TID'] ?? '');

        // Build the content: info text + embedded map with this feature's geometry
        $content = $this->buildContent($info, $feature);

        return new ServiceInfoItem(
            sourceId: (string) $objectId,
            title: $title,
            content: $content,
            startDate: $startDate ?? $publishDate,
            endDate: $endDate,
            categories: ['Trafikstörningar'],
            unpublishDate: $unpublishDate,
            onUnpublish: 'trash',
            publishDate: $publishDate,
        );
    }

    /**
     * Build the post content from the info text and an embedded map.
     */
    private function buildContent(string $info, array $feature): string
    {
        $content = '';

        if (!empty($info)) {
            $content .= '<p>' . esc_html($info) . '</p>';
        }

        // Build a single-feature GeoJSON FeatureCollection for the map
        $centroid    = $this->calculateCentroid($feature['geometry'] ?? []);
        $geoJsonData = [
            'type'     => 'FeatureCollection',
            'features' => [$feature],
        ];

        if (class_exists(\ModularityArcgisMap\Helper\MapRenderer::class)) {
            $content .= '<h2 class="c-typography c-typography__variant--h3">' . __('Map showing traffic disruption', 'pitea-customisation') . '</h2>';
            $content .= \ModularityArcgisMap\Helper\MapRenderer::render([
                'lat'          => (string) $centroid['lat'],
                'lng'          => (string) $centroid['lng'],
                'zoom'         => 15,
                'showMarker'   => false,
                'height'       => '400px',
                'geoJsonData'  => $geoJsonData,
                'geoJsonTitle' => __('Traffic disruption location', 'pitea-customisation'),
            ]);
        }

        return $content;
    }

    /**
     * Convert a millisecond Unix timestamp (GMT) to a DateTimeImmutable in the WP local timezone.
     */
    private function convertTimestamp(int|string|null $timestampMs): ?\DateTimeImmutable
    {
        if ($timestampMs === null || $timestampMs === '' || $timestampMs === 0) {
            return null;
        }

        $seconds = (int) ($timestampMs / 1000);

        return (new \DateTimeImmutable('@' . $seconds))->setTimezone(wp_timezone());
    }

    /**
     * Parse the TID string (format: "YYYY-MM-DD - YYYY-MM-DD") into a start and end DateTimeImmutable.
     *
     * @return array{0: \DateTimeImmutable|null, 1: \DateTimeImmutable|null}
     */
    private function parseTidField(string $tid): array
    {
        $tz        = wp_timezone();
        $parts     = explode(' - ', $tid, 2);
        $startDate = null;
        $endDate   = null;

        if (!empty($parts[0])) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', trim($parts[0]), $tz);
            if ($dt !== false) {
                $startDate = $dt->setTime(0, 0, 0);
            }
        }

        if (!empty($parts[1])) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', trim($parts[1]), $tz);
            if ($dt !== false) {
                $endDate = $dt->setTime(23, 59, 59);
            }
        }

        return [$startDate, $endDate];
    }

    /**
     * Calculate a rough centroid from a GeoJSON geometry (for map centering).
     *
     * @return array{lat: float, lng: float}
     */
    private function calculateCentroid(array $geometry): array
    {
        $coords = $this->extractCoordinates($geometry);

        if (empty($coords)) {
            return ['lat' => 0, 'lng' => 0];
        }

        $lngSum = 0;
        $latSum = 0;

        foreach ($coords as [$lng, $lat]) {
            $lngSum += $lng;
            $latSum += $lat;
        }

        $count = count($coords);

        return [
            'lat' => $latSum / $count,
            'lng' => $lngSum / $count,
        ];
    }

    /**
     * Recursively extract [lng, lat] coordinate pairs from a GeoJSON geometry.
     *
     * @return array<array{0: float, 1: float}>
     */
    private function extractCoordinates(array $geometry): array
    {
        $type   = $geometry['type'] ?? '';
        $coords = $geometry['coordinates'] ?? [];

        return match ($type) {
            'Point'      => [$coords],
            'LineString', 'MultiPoint' => $coords,
            'Polygon', 'MultiLineString' => array_merge(...$coords),
            'MultiPolygon' => array_merge(...array_merge(...$coords)),
            default => [],
        };
    }
}
