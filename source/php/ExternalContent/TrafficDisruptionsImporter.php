<?php

declare(strict_types=1);

namespace PiteaCustomisation\ExternalContent;

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
    private const SOURCE_URL = 'https://gisportal.pitea.se/arcgis/rest/services/PK/PK_Trafikstorningar_Publik/FeatureServer/3/query?where=1=1&outFields=*&f=geojson';

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
        $geoJson = $this->fetchGeoJson();

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
     * @return array Decoded GeoJSON FeatureCollection
     * @throws \RuntimeException On HTTP or parse errors
     */
    private function fetchGeoJson(): array
    {
        $response = wp_remote_get(self::SOURCE_URL, [
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

        $startDate = $this->convertTimestamp($props['PUBLICERA_START'] ?? null);
        $endDate   = $this->convertTimestamp($props['PUBLICERA_SLUT'] ?? null);

        if ($startDate === null) {
            return null;
        }

        // Build the content: info text + embedded map with this feature's geometry
        $content = $this->buildContent($info, $feature);

        return new ServiceInfoItem(
            sourceId: (string) $objectId,
            title: $title,
            content: $content,
            startDate: $startDate,
            endDate: $endDate,
            categories: ['Trafikstörningar'],
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
     * Convert a millisecond Unix timestamp to a DateTimeImmutable.
     */
    private function convertTimestamp(int|string|null $timestampMs): ?\DateTimeImmutable
    {
        if ($timestampMs === null || $timestampMs === '' || $timestampMs === 0) {
            return null;
        }

        $seconds = (int) ($timestampMs / 1000);

        return (new \DateTimeImmutable())->setTimestamp($seconds);
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
