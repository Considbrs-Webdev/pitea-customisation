<?php

declare(strict_types=1);

namespace PiteaCustomisation\ExternalContent\Search\EServices;

use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Indexing\Strategies\AbstractExternalIndexingStrategy;

/**
 * Class EServicesImporter
 *
 * Indexes Piteå's public e-services from the eNämnd public-services API into
 * the Typesense search index.
 *
 * ── API data structure ────────────────────────────────────────────────────
 *
 *   {
 *     "Services": [
 *       {
 *         "Name":     "...",
 *         "FamilyID": "1234",
 *         "Category": "Bygga, bo och miljö",
 *         "Profile":  "Piteå",
 *         "Hostname": "https://pitea.enamnd.se/oversikt/overview/1234",
 *         "Type":     "internal" | "external"
 *       },
 *       ...
 *     ]
 *   }
 *
 * ── Document ID namespacing ───────────────────────────────────────────────
 *
 * Documents are prefixed with 'pitea-eservice-' (e.g. 'pitea-eservice-1234')
 * to avoid colliding with WordPress post IDs (plain integers) in the shared
 * Typesense collection.
 *
 * ── Stale document cleanup ────────────────────────────────────────────────
 *
 * Because e-services have no WordPress lifecycle hooks, records removed from
 * the source API would otherwise remain in the index forever. syncAll()
 * therefore:
 *
 *   1. Fetches all items from the API and upserts them.
 *   2. Queries Typesense for every document whose type = TYPE_IDENTIFIER.
 *   3. Deletes any document whose ID is absent from the current API response.
 *
 * The cleanup query is paginated so it handles collections of any size.
 *
 * ── Triggering a sync ─────────────────────────────────────────────────────
 *
 * A daily WP-Cron event ('pitea_typesense_sync_eservices') is scheduled on
 * `init` via registerHooks(). You can also run a sync manually with WP-CLI:
 *
 *   wp typesense sync-external pitea-eservice
 *
 * @package PiteaCustomisation\ExternalContent\Search\EServices
 */
class EServicesImporter extends AbstractExternalIndexingStrategy
{
    /**
     * WordPress option key that stores the configured API source URL.
     * Managed via Settings → Piteå Customisation → External Content.
     */
    public const OPTION_SOURCE_URL = 'pitea_customisation_eservices_source_url';

    /**
     * WP-Cron hook name. Reuse this when clearing the event on plugin uninstall.
     */
    public const CRON_HOOK = 'pitea_typesense_sync_eservices';

    /**
     * Value written to the Typesense 'type' field for every e-service document.
     * Used by removeStaleDocuments() to scope queries to this strategy's docs.
     */
    private const TYPE_IDENTIFIER = 'pitea-eservice';

    // ── ExternalIndexingStrategyInterface ─────────────────────────────────

    public function getIdentifier(): string
    {
        return 'pitea-eservice';
    }

    /**
     * Schedule a daily cron sync and wire up the action handler.
     *
     * {@inheritdoc}
     */
    public function registerHooks(): void
    {
        add_action('init', function (): void {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time(), 'daily', self::CRON_HOOK);
            }
        });

        add_action(self::CRON_HOOK, [$this, 'syncAll']);
    }

    // ── syncAll override ──────────────────────────────────────────────────

    /**
     * Fetch, upsert, and clean up stale e-service documents.
     *
     * Overrides the parent so we can:
     *  a) bail early when no source URL is configured, and
     *  b) remove documents that the source API no longer returns.
     *
     * {@inheritdoc}
     */
    public function syncAll(): int
    {
        $sourceUrl = (string) get_option(self::OPTION_SOURCE_URL, '');

        if (empty($sourceUrl)) {
            return 0;
        }

        $client         = $this->getClient();
        $collectionName = $this->getCollectionName();

        if ($client === null || $collectionName === '') {
            return 0;
        }

        // 1. Collect all items and the set of IDs we expect to be in the index.
        $items       = [];
        $expectedIds = [];

        foreach ($this->fetchItems() as $item) {
            $items[]                            = $item;
            $expectedIds[$this->getExternalId($item)] = true;
        }

        if (empty($items)) {
            return 0;
        }

        // 2. Upsert every item into the index.
        $indexed = 0;

        foreach ($items as $item) {
            $document = $this->buildDocument($item);

            if ($document === false) {
                error_log(sprintf(
                    '[PiteaCustomisation][%s] buildDocument() returned false, skipping item.',
                    $this->getIdentifier()
                ));
                continue;
            }

            try {
                $client->collections[$collectionName]->documents->upsert($document->toArray());
                $indexed++;
            } catch (\Exception $e) {
                error_log(sprintf(
                    '[PiteaCustomisation][%s] Failed to index document "%s": %s',
                    $this->getIdentifier(),
                    $document->get('id'),
                    $e->getMessage()
                ));
            }
        }

        // 3. Remove any documents that are no longer in the source.
        $this->removeStaleDocuments($client, $collectionName, $expectedIds);

        return $indexed;
    }

    // ── AbstractExternalIndexingStrategy ──────────────────────────────────

    /**
     * Fetch all e-services from the configured API endpoint.
     *
     * Returns an empty array on network or parse failure so syncAll() exits
     * cleanly (0 items indexed) rather than throwing.
     *
     * {@inheritdoc}
     */
    protected function fetchItems(): iterable
    {
        $sourceUrl = (string) get_option(self::OPTION_SOURCE_URL, '');

        if (empty($sourceUrl)) {
            return [];
        }

        $response = wp_remote_get($sourceUrl, ['timeout' => 30]);

        if (is_wp_error($response)) {
            error_log(sprintf(
                '[PiteaCustomisation][%s] API request failed: %s',
                $this->getIdentifier(),
                $response->get_error_message()
            ));
            return [];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            error_log(sprintf(
                '[PiteaCustomisation][%s] API returned HTTP %d.',
                $this->getIdentifier(),
                $statusCode
            ));
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['Services']) || !is_array($data['Services'])) {
            error_log(sprintf(
                '[PiteaCustomisation][%s] API returned unexpected JSON structure.',
                $this->getIdentifier()
            ));
            return [];
        }

        return $data['Services'];
    }

    /**
     * Build a Typesense document from one e-service record.
     *
     * Returns false when required fields are missing so the item is skipped.
     *
     * Field mapping:
     *   Name     → title
     *   FamilyID → id suffix (namespaced as 'pitea-eservice-{FamilyID}')
     *   Category → tags (single-item array for faceting)
     *   Hostname → url
     *   Type     → eservice_type ('internal' | 'external')
     *
     * {@inheritdoc}
     */
    protected function buildDocument(mixed $item): IndexableDocument|false
    {
        $name     = isset($item['Name'])     ? trim((string) $item['Name'])     : '';
        $familyId = isset($item['FamilyID']) ? trim((string) $item['FamilyID']) : '';

        if ($name === '' || $familyId === '') {
            return false;
        }

        $category = isset($item['Category']) ? trim((string) $item['Category']) : '';
        $url      = isset($item['Hostname']) ? trim((string) $item['Hostname']) : '';
        $type     = isset($item['Type'])     ? trim((string) $item['Type'])     : '';

        return new IndexableDocument([
            'id'             => $this->getExternalId($item),
            'title'          => $name,
            'content'        => '',
            'excerpt'        => $category,
            'url'            => $url,
            'type'           => self::TYPE_IDENTIFIER,
            'type_name'      => __('E-services', 'pitea-customisation'),
            'eservice_type'  => $type,
            'tags'           => $category !== '' ? [$category] : [],
            'date'           => 0,
        ]);
    }

    /**
     * Return the namespaced Typesense document ID for an e-service item.
     *
     * Format: 'pitea-eservice-{FamilyID}'
     *
     * {@inheritdoc}
     */
    protected function getExternalId(mixed $item): string
    {
        return 'pitea-eservice-' . $item['FamilyID'];
    }

    // ── Stale document cleanup ─────────────────────────────────────────────

    /**
     * Query Typesense for all documents of this strategy's type and delete
     * any whose ID is absent from the freshly-fetched source data.
     *
     * Results are paginated (250 per page) to handle large collections without
     * memory pressure. Individual delete failures are logged and skipped.
     *
     * @param mixed                $client         Typesense client instance.
     * @param string               $collectionName Typesense collection name.
     * @param array<string, true>  $expectedIds    Set of IDs currently in the source.
     */
    private function removeStaleDocuments(mixed $client, string $collectionName, array $expectedIds): void
    {
        $page    = 1;
        $perPage = 250;
        $found   = null;

        do {
            try {
                $result = $client->collections[$collectionName]->documents->search([
                    'q'              => '*',
                    'query_by'       => 'title',
                    'filter_by'      => 'type:=' . self::TYPE_IDENTIFIER,
                    'per_page'       => $perPage,
                    'page'           => $page,
                    'include_fields' => 'id',
                ]);
            } catch (\Exception $e) {
                error_log(sprintf(
                    '[PiteaCustomisation][%s] Stale-cleanup query failed (page %d): %s',
                    $this->getIdentifier(),
                    $page,
                    $e->getMessage()
                ));
                break;
            }

            // Capture total on first page so the loop knows when to stop.
            if ($found === null) {
                $found = (int) ($result['found'] ?? 0);
            }

            foreach ($result['hits'] ?? [] as $hit) {
                $docId = $hit['document']['id'] ?? null;

                if ($docId === null) {
                    continue;
                }

                if (!isset($expectedIds[$docId])) {
                    $this->deindex($docId);
                    error_log(sprintf(
                        '[PiteaCustomisation][%s] Removed stale document "%s".',
                        $this->getIdentifier(),
                        $docId
                    ));
                }
            }

            $page++;
        } while (($page - 1) * $perPage < $found);
    }
}
