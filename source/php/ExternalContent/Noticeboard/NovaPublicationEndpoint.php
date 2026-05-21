<?php

declare(strict_types=1);

namespace PiteaCustomisation\ExternalContent\Noticeboard;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class NovaPublicationEndpoint
{
    public const REST_NAMESPACE = 'nova/v1';
    public const REST_ROUTE = '/publish';
    public const USERNAME_CONSTANT = 'SOKIGO_NOVA_PUBLISH_USERNAME';
    public const PASSWORD_CONSTANT = 'SOKIGO_NOVA_PUBLISH_PASSWORD';

    private const NOTICE_POST_TYPE = 'noticeboard_notice';
    private const NOTICE_TAXONOMY = 'noticeboard_notice_type';
    private const META_NOVA_ID = '_pitea_nova_publication_id';
    private const META_NOVA_TYPE = '_pitea_nova_publication_type';
    private const META_NOVA_PAYLOAD = '_pitea_nova_publication_payload';
    private const ACF_ARCHIVE_DATE_FIELD = 'field_69679a808b9be';
    private const ACF_ARCHIVE_TIME_FIELD = 'field_6a0eeddaa1aa7';

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoute']);
    }

    public static function isConfigured(): bool
    {
        return self::getExpectedUsername() !== '' && self::getExpectedPassword() !== '';
    }

    public static function getEndpointUrl(): string
    {
        return rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
    }

    public function registerRoute(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleRequest'],
                'permission_callback' => [$this, 'authenticateRequest'],
            ]
        );
    }

    public function authenticateRequest(WP_REST_Request $request): true|WP_Error
    {
        if (!self::isConfigured()) {
            return new WP_Error(
                'nova_not_configured',
                __('Sokigo Nova publication endpoint is not configured.', 'pitea-customisation'),
                ['status' => 401]
            );
        }

        $authorization = $this->getAuthorizationHeader($request);

        if (stripos($authorization, 'Basic ') !== 0) {
            return new WP_Error(
                'nova_missing_authorization',
                __('Missing Authorization header.', 'pitea-customisation'),
                ['status' => 401]
            );
        }

        $decoded = base64_decode(substr($authorization, 6), true);

        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return new WP_Error(
                'nova_invalid_authorization',
                __('Invalid Authorization header.', 'pitea-customisation'),
                ['status' => 401]
            );
        }

        [$username, $password] = explode(':', $decoded, 2);

        if (
            !hash_equals(self::getExpectedUsername(), $username) ||
            !hash_equals(self::getExpectedPassword(), $password)
        ) {
            return new WP_Error(
                'nova_invalid_credentials',
                __('Invalid credentials.', 'pitea-customisation'),
                ['status' => 401]
            );
        }

        return true;
    }

    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            return new WP_Error(
                'nova_invalid_json',
                __('Request body must be valid JSON.', 'pitea-customisation'),
                ['status' => 400]
            );
        }

        $validationError = $this->validatePayload($payload);
        if ($validationError instanceof WP_Error) {
            return $validationError;
        }

        if (!post_type_exists(self::NOTICE_POST_TYPE) || !taxonomy_exists(self::NOTICE_TAXONOMY)) {
            return new WP_Error(
                'nova_noticeboard_unavailable',
                __('The digital noticeboard post type or taxonomy is not available.', 'pitea-customisation'),
                ['status' => 500]
            );
        }

        $termIds = $this->ensureNoticeTypeTerms((int) $payload['type']);
        if ($termIds instanceof WP_Error) {
            return $termIds;
        }

        $postId = $this->upsertNotice($payload, $termIds);
        if ($postId instanceof WP_Error) {
            return $postId;
        }

        $response = [
            'success' => true,
            'post_id' => $postId,
        ];

        return new WP_REST_Response($response, 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePayload(array $payload): ?WP_Error
    {
        foreach (['type', 'id', 'title', 'content', 'publishDate'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                return new WP_Error(
                    'nova_missing_required_field',
                    sprintf(
                        /* translators: %s: field name. */
                        __('Missing required field: %s.', 'pitea-customisation'),
                        $field
                    ),
                    ['status' => 400]
                );
            }
        }

        if (!is_numeric($payload['type'])) {
            return new WP_Error(
                'nova_invalid_type',
                __('The type field must be 1, 2, or 3.', 'pitea-customisation'),
                ['status' => 400]
            );
        }

        $type = (int) $payload['type'];
        if (!in_array($type, [1, 2, 3], true)) {
            return new WP_Error(
                'nova_invalid_type',
                __('The type field must be 1, 2, or 3.', 'pitea-customisation'),
                ['status' => 400]
            );
        }

        foreach (['publishDate', 'publishEndDate', 'decisionDate', 'responseDate'] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                continue;
            }

            if (!$this->isUnixTimestamp($payload[$field])) {
                return new WP_Error(
                    'nova_invalid_timestamp',
                    sprintf(
                        /* translators: %s: field name. */
                        __('The %s field must be a Unix timestamp.', 'pitea-customisation'),
                        $field
                    ),
                    ['status' => 400]
                );
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param int[]                $termIds
     */
    private function upsertNotice(array $payload, array $termIds): int|WP_Error
    {
        $novaId = sanitize_text_field((string) $payload['id']);
        $novaType = (int) $payload['type'];
        $existingPostId = $this->findExistingNoticeId($novaId, $novaType);
        $publishDate = $this->timestampToLocalDateTime((int) $payload['publishDate']);
        $archiveDateTime = $this->getArchiveDateTime($payload);

        $postData = [
            'post_type' => self::NOTICE_POST_TYPE,
            'post_status' => $publishDate->getTimestamp() > time() ? 'future' : 'publish',
            'post_title' => sanitize_text_field((string) $payload['title']),
            'post_content' => $this->buildPostContent($payload),
            'post_date' => $publishDate->format('Y-m-d H:i:s'),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $publishDate->getTimestamp()),
        ];

        if ($existingPostId > 0) {
            $postData['ID'] = $existingPostId;
            $postId = wp_update_post(wp_slash($postData), true);
        } else {
            $postId = wp_insert_post(wp_slash($postData), true);
        }

        if (is_wp_error($postId)) {
            return new WP_Error(
                'nova_notice_save_failed',
                $postId->get_error_message(),
                ['status' => 500]
            );
        }

        $postId = (int) $postId;

        wp_set_object_terms($postId, $termIds, self::NOTICE_TAXONOMY, false);

        update_post_meta($postId, self::META_NOVA_ID, $novaId);
        update_post_meta($postId, self::META_NOVA_TYPE, $novaType);
        update_post_meta($postId, self::META_NOVA_PAYLOAD, wp_json_encode($payload));

        if ($archiveDateTime !== null) {
            update_post_meta($postId, 'archive_date', $archiveDateTime->format('Y-m-d'));
            update_post_meta($postId, 'archive_time', $archiveDateTime->format('H:i'));
        } else {
            delete_post_meta($postId, 'archive_date');
            delete_post_meta($postId, 'archive_time');
        }

        if (function_exists('update_field')) {
            update_field(
                self::ACF_ARCHIVE_DATE_FIELD,
                $archiveDateTime !== null ? $archiveDateTime->format('Y-m-d') : '',
                $postId
            );
            update_field(
                self::ACF_ARCHIVE_TIME_FIELD,
                $archiveDateTime !== null ? $archiveDateTime->format('H:i') : '',
                $postId
            );
        }

        return $postId;
    }

    private function findExistingNoticeId(string $novaId, int $novaType): int
    {
        $posts = get_posts([
            'post_type'              => self::NOTICE_POST_TYPE,
            'post_status'            => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'meta_query'             => [
                [
                    'key'     => self::META_NOVA_ID,
                    'value'   => $novaId,
                    'compare' => '=',
                ],
                [
                    'key'     => self::META_NOVA_TYPE,
                    'value'   => $novaType,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
            'no_found_rows'          => true,
            'suppress_filters'       => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    /**
     * @return int[]|WP_Error
     */
    private function ensureNoticeTypeTerms(int $novaType): array|WP_Error
    {
        $termNames = [];

        if ($novaType === 1) {
            $termNames[] = 'Kungörelser';
            $termNames[] = 'Bygglov';
        } elseif ($novaType === 2) {
            $termNames[] = 'Beslut';
            $termNames[] = 'Bygglov';
        } elseif ($novaType === 3) {
            $termNames[] = 'Bygglov';
        }

        $termIds = [];

        foreach ($termNames as $termName) {
            $termId = $this->ensureNoticeTypeTerm($termName);

            if ($termId instanceof WP_Error) {
                return $termId;
            }

            $termIds[] = $termId;
        }

        return $termIds;
    }

    private function ensureNoticeTypeTerm(string $termName): int|WP_Error
    {
        $term = term_exists($termName, self::NOTICE_TAXONOMY);

        if ($term === 0 || $term === null) {
            $term = wp_insert_term(
                $termName,
                self::NOTICE_TAXONOMY,
                ['slug' => sanitize_title($termName)]
            );
        }

        if (is_wp_error($term)) {
            return new WP_Error(
                'nova_notice_type_failed',
                $term->get_error_message(),
                ['status' => 500]
            );
        }

        return (int) (is_array($term) ? $term['term_id'] : $term);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildPostContent(array $payload): string
    {
        $content = wpautop(wp_kses_post((string) $payload['content']));
        $details = [];

        if ((int) $payload['type'] === 1) {
            $publicNotification = $this->getStringValue($payload, 'publicNotification');
            if ($publicNotification !== '') {
                $content .= wpautop(esc_html($publicNotification));
            }
        }

        $details[] = $this->buildDetailPart(__('Case ID', 'pitea-customisation'), sanitize_text_field((string) $payload['id']));
        $details[] = $this->buildDetailPart(__('Estate', 'pitea-customisation'), $this->getStringValue($payload, 'estate'));
        $details[] = $this->buildDetailPart(__('Decision', 'pitea-customisation'), $this->getStringValue($payload, 'decision'));
        $details[] = $this->buildDetailPart(__('Decision number', 'pitea-customisation'), $this->getStringValue($payload, 'decisionNumber'));
        $details[] = $this->buildDetailPart(__('Decision date', 'pitea-customisation'), $this->formatTimestamp($payload['decisionDate'] ?? null));
        $details[] = $this->buildDetailPart(__('Response date', 'pitea-customisation'), $this->formatTimestamp($payload['responseDate'] ?? null));

        $detailsHtml = implode('<br>', array_filter($details));

        if ($detailsHtml !== '') {
            $content .= '<p>' . $detailsHtml . '</p>';
        }

        return $content;
    }

    private function buildDetailPart(string $label, string $value): string
    {
        if ($value === '') {
            return '';
        }

        return sprintf(
            '<strong>%s:</strong> %s',
            esc_html($label),
            esc_html($value)
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getArchiveDateTime(array $payload): ?\DateTimeImmutable
    {
        $timestamp = $payload['publishEndDate'] ?? null;

        if (!$this->isUnixTimestamp($timestamp)) {
            return null;
        }

        return $this->timestampToLocalDateTime((int) $timestamp);
    }

    private function formatTimestamp(mixed $timestamp, string $format = ''): string
    {
        if (!$this->isUnixTimestamp($timestamp)) {
            return '';
        }

        $format = $format !== '' ? $format : (string) get_option('date_format');

        return wp_date($format, (int) $timestamp, wp_timezone());
    }

    private function timestampToLocalDateTime(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(wp_timezone());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getStringValue(array $payload, string $key): string
    {
        if (!isset($payload[$key])) {
            return '';
        }

        return trim(sanitize_text_field((string) $payload[$key]));
    }

    private function isUnixTimestamp(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_float($value)) {
            return $value > 0;
        }

        if (!is_string($value)) {
            return false;
        }

        return ctype_digit($value) && (int) $value > 0;
    }

    private function getAuthorizationHeader(WP_REST_Request $request): string
    {
        $authorization = $request->get_header('authorization');
        if (is_string($authorization) && $authorization !== '') {
            return $authorization;
        }

        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }
        }

        return '';
    }

    private static function getExpectedUsername(): string
    {
        return defined(self::USERNAME_CONSTANT) ? trim((string) constant(self::USERNAME_CONSTANT)) : '';
    }

    private static function getExpectedPassword(): string
    {
        return defined(self::PASSWORD_CONSTANT) ? trim((string) constant(self::PASSWORD_CONSTANT)) : '';
    }
}
