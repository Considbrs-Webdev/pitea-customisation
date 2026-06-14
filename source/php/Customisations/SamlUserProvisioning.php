<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

class SamlUserProvisioning
{
    private const USER_GROUP_TAXONOMY = 'user_group';
    private const CLAIM_DISPLAY_NAME = 'http://schemas.microsoft.com/identity/claims/displayname';
    private const CLAIM_GROUPS = 'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups';
    private const CLAIM_GIVEN_NAME = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname';
    private const CLAIM_SURNAME = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname';
    private const CLAIM_EMAIL = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress';
    private const CLAIM_ONPREMISES_SAM_ACCOUNT_NAME = 'user.onpremisessamaccountname';

    /**
     * @var array{role: string, account_name: string, user_group_id: string, user_group_name: string}|null
     */
    private ?array $activeLogin = null;

    public function __construct()
    {
        add_action('init', [$this, 'ensureMiniOrangeAttributeMapping'], 0);
        add_action('init', [$this, 'deleteNonAssignableUserGroupTerms'], 100);
        add_action('mo_saml_user_attributes', [$this, 'validateUserAttributes'], 1);
        add_action('mo_saml_user_group_name', [$this, 'applyRoleAndUserGroup'], 10, 2);
        add_action('set_auth_cookie', [$this, 'applyRoleAndUserGroupAfterAuthCookie'], PHP_INT_MAX, 6);
    }

    public function ensureMiniOrangeAttributeMapping(): void
    {
        $requiredOptions = [
            'saml_am_email' => self::CLAIM_EMAIL,
            'saml_am_username' => self::CLAIM_EMAIL,
            'saml_am_first_name' => self::CLAIM_GIVEN_NAME,
            'saml_am_last_name' => self::CLAIM_SURNAME,
            'saml_am_group_name' => self::CLAIM_GROUPS,
            'saml_am_account_matcher' => 'email',
        ];

        foreach ($requiredOptions as $option => $value) {
            if (get_option($option) !== $value) {
                update_option($option, $value);
            }
        }
    }

    public function validateUserAttributes(array $attrs): void
    {
        $accountName = $this->extractAccountName($this->getFirstAttributeValue($attrs, self::CLAIM_ONPREMISES_SAM_ACCOUNT_NAME));
        $email = sanitize_email($this->getFirstAttributeValue($attrs, self::CLAIM_EMAIL));

        if ($accountName === '') {
            $this->denyLogin(__('Your SAML response is missing a valid on-premises account name.', 'pitea-customisation'));
        }

        if ($email === '') {
            $this->denyLogin(__('Your SAML response is missing a valid email address.', 'pitea-customisation'));
        }

        $groups = (array) ($attrs[self::CLAIM_GROUPS] ?? []);
        $match = $this->findFirstAllowedGroup($groups);

        if ($match === null) {
            $this->denyLogin(__('Your account is not a member of an allowed web group.', 'pitea-customisation'));
        }

        $userGroupMatch = $this->findFirstUserGroupMatch($groups);

        $this->activeLogin = [
            'role' => $match['role'],
            'account_name' => $accountName,
            'user_group_id' => $userGroupMatch['group_id'] ?? '',
            'user_group_name' => $userGroupMatch['name'] ?? '',
        ];

        $userId = $this->ensureUser($attrs, $accountName, $email);
        $this->grantAccess($userId, $this->activeLogin);
    }

    /**
     * @param array<int, string>|string $groupNames
     */
    public function applyRoleAndUserGroup($userId, $groupNames): void
    {
        $userId = (int) $userId;

        if ($userId <= 0 || $this->activeLogin === null) {
            return;
        }

        $this->grantAccess($userId, $this->activeLogin);
    }

    public function applyRoleAndUserGroupAfterAuthCookie(
        string $authCookie,
        int $expire,
        int $expiration,
        int $userId,
        string $scheme,
        string $token
    ): void {
        $this->applyRoleAndUserGroup($userId, []);
    }

    private function ensureUser(array $attrs, string $accountName, string $email): int
    {
        $existingByEmail = email_exists($email);
        $existingByLogin = username_exists($accountName);

        if ($existingByEmail && $existingByLogin && (int) $existingByEmail !== (int) $existingByLogin) {
            $this->denyLogin(__('The SAML email and account name belong to different WordPress users.', 'pitea-customisation'));
        }

        if ($existingByEmail) {
            $userId = (int) $existingByEmail;
            $this->updateUserProfile($userId, $attrs, $email);
            return $userId;
        }

        if ($existingByLogin) {
            $this->denyLogin(__('The SAML account name already belongs to another WordPress user.', 'pitea-customisation'));
        }

        $userId = wp_insert_user([
            'user_login' => $accountName,
            'user_pass' => wp_generate_password(32, true),
            'user_email' => $email,
            'first_name' => $this->getFirstAttributeValue($attrs, self::CLAIM_GIVEN_NAME),
            'last_name' => $this->getFirstAttributeValue($attrs, self::CLAIM_SURNAME),
            'display_name' => $this->getFirstAttributeValue($attrs, self::CLAIM_DISPLAY_NAME),
            'role' => $this->activeLogin['role'] ?? 'editor',
        ]);

        if (is_wp_error($userId)) {
            $this->denyLogin($userId->get_error_message());
        }

        return (int) $userId;
    }

    private function updateUserProfile(int $userId, array $attrs, string $email): void
    {
        $userData = [
            'ID' => $userId,
            'user_email' => $email,
        ];

        $firstName = $this->getFirstAttributeValue($attrs, self::CLAIM_GIVEN_NAME);
        $lastName = $this->getFirstAttributeValue($attrs, self::CLAIM_SURNAME);
        $displayName = $this->getFirstAttributeValue($attrs, self::CLAIM_DISPLAY_NAME);

        if ($firstName !== '') {
            $userData['first_name'] = $firstName;
        }

        if ($lastName !== '') {
            $userData['last_name'] = $lastName;
        }

        if ($displayName !== '') {
            $userData['display_name'] = $displayName;
        }

        $updated = wp_update_user($userData);
        if (is_wp_error($updated)) {
            $this->denyLogin($updated->get_error_message());
        }
    }

    /**
     * @param array{role: string, user_group_id: string, user_group_name: string} $match
     */
    private function grantAccess(int $userId, array $match): void
    {
        $user = get_userdata($userId);
        if (!$user) {
            $this->denyLogin(__('The WordPress user could not be loaded.', 'pitea-customisation'));
        }

        $user->set_role($match['role']);

        if ($match['user_group_id'] === '') {
            $this->deleteNonAssignableUserGroupTerms();
            return;
        }

        $termId = $this->getOrCreateUserGroupTerm($match['user_group_id'], $match['user_group_name']);
        if ($termId <= 0) {
            $this->denyLogin(__('The user group could not be created or loaded.', 'pitea-customisation'));
        }

        $this->assignSingleUserGroup($userId, $termId);
        $this->deleteNonAssignableUserGroupTerms();
    }

    private function getOrCreateUserGroupTerm(string $groupId, string $name): int
    {
        return $this->withUserGroupBlog(function () use ($groupId, $name): int {
            $term = get_term_by('slug', $groupId, self::USER_GROUP_TAXONOMY);

            if ($term && !is_wp_error($term)) {
                if ($term->name === $groupId) {
                    wp_update_term((int) $term->term_id, self::USER_GROUP_TAXONOMY, [
                        'name' => $name,
                    ]);
                }

                return (int) $term->term_id;
            }

            $created = wp_insert_term($name, self::USER_GROUP_TAXONOMY, [
                'slug' => $groupId,
            ]);

            if (is_wp_error($created)) {
                $existingTermId = (int) $created->get_error_data('term_exists');
                return $existingTermId > 0 ? $existingTermId : 0;
            }

            return (int) ($created['term_id'] ?? 0);
        });
    }

    private function assignSingleUserGroup(int $userId, int $termId): void
    {
        $this->withUserGroupBlog(function () use ($userId, $termId): void {
            wp_delete_object_term_relationships($userId, [self::USER_GROUP_TAXONOMY]);

            $result = wp_set_object_terms($userId, [$termId], self::USER_GROUP_TAXONOMY, false);

            if (is_wp_error($result)) {
                $this->denyLogin($result->get_error_message());
            }
        });
    }

    public function deleteNonAssignableUserGroupTerms(): void
    {
        $this->withUserGroupBlog(function (): void {
            if (!taxonomy_exists(self::USER_GROUP_TAXONOMY)) {
                return;
            }

            foreach ($this->getAllowedGroups() as $group) {
                if ($group['add_to_user_group']) {
                    continue;
                }

                $term = get_term_by('slug', $group['group_id'], self::USER_GROUP_TAXONOMY);
                if (!$term || is_wp_error($term)) {
                    continue;
                }

                wp_delete_term((int) $term->term_id, self::USER_GROUP_TAXONOMY);
            }
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withUserGroupBlog(callable $callback)
    {
        if (!is_multisite()) {
            return $callback();
        }

        switch_to_blog(get_main_site_id());

        try {
            return $callback();
        } finally {
            restore_current_blog();
        }
    }

    private function extractAccountName(string $distinguishedName): string
    {
        if (!preg_match('/(?:^|,)CN=([^,]+)/i', $distinguishedName, $matches)) {
            return '';
        }

        return sanitize_user($matches[1], true);
    }

    private function getFirstAttributeValue(array $attrs, string $key): string
    {
        if (empty($attrs[$key])) {
            return '';
        }

        $value = is_array($attrs[$key]) ? reset($attrs[$key]) : $attrs[$key];

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<int, string> $userGroupIds
     * @return array{group_id: string, name: string, role: string, add_to_user_group: bool}|null
     */
    private function findFirstAllowedGroup(array $userGroupIds): ?array
    {
        $normalizedUserGroupIds = array_flip(array_map('strtolower', array_map('strval', $userGroupIds)));

        foreach ($this->getAllowedGroups() as $group) {
            if (isset($normalizedUserGroupIds[strtolower($group['group_id'])])) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $userGroupIds
     * @return array{group_id: string, name: string, role: string, add_to_user_group: bool}|null
     */
    private function findFirstUserGroupMatch(array $userGroupIds): ?array
    {
        $eligibleGroups = [];

        foreach ($this->getAllowedGroups() as $group) {
            if ($group['add_to_user_group']) {
                $eligibleGroups[strtolower($group['group_id'])] = $group;
            }
        }

        foreach ($userGroupIds as $groupId) {
            $normalizedGroupId = strtolower((string) $groupId);

            if (isset($eligibleGroups[$normalizedGroupId])) {
                return $eligibleGroups[$normalizedGroupId];
            }
        }

        return null;
    }

    /**
     * @return list<array{group_id: string, name: string, role: string, add_to_user_group: bool}>
     */
    private function getAllowedGroups(): array
    {
        return [
            [
                'name' => 'WEB_ADMIN',
                'group_id' => '82d935d3-a876-46f3-90a7-a62f710b50f8',
                'role' => 'administrator',
                'add_to_user_group' => false,
            ],
            [
                'name' => 'Webbredaktörer Piteå kommun',
                'group_id' => '2d12ce30-4094-4224-8525-fdd8fab092bd',
                'role' => 'editor',
                'add_to_user_group' => false,
            ],
            [
                'name' => 'WEB_BLOGGARE',
                'group_id' => '09d1a1d4-7902-40dd-8a1d-f385819b0c30',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb / Lärcentrum',
                'group_id' => 'f725d815-754c-4b02-ab82-e7adb02dd682',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb-Konsulter-Consid',
                'group_id' => 'd5dcfa54-138d-44ff-8b37-8002e9e54bb6',
                'role' => 'editor',
                'add_to_user_group' => false,
            ],
            [
                'name' => 'Webb_Redaktör',
                'group_id' => '9d2e3e41-7346-4348-9441-5aa9f6cb3921',
                'role' => 'editor',
                'add_to_user_group' => false,
            ],
            [
                'name' => 'Webb_Skribent',
                'group_id' => 'bfb8d7d7-9bdf-49d9-8125-3c16cac8304b',
                'role' => 'editor',
                'add_to_user_group' => false,
            ],
            [
                'name' => 'Webb_Skribent_FSF',
                'group_id' => '89f9b42c-2315-4410-aae4-d8f4d30df861',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_FSK',
                'group_id' => 'eeb20e0b-844f-4915-9ea5-5bed11280082',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_Grans',
                'group_id' => '626ee7eb-4401-4b4c-8e18-eb3ff16d5280',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_KLF',
                'group_id' => '261e92ad-d6d0-49bd-bda0-05fc96b80290',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_KPF',
                'group_id' => 'eee10992-705c-4442-85e3-90d978a8f8a5',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_RTJ',
                'group_id' => '19150f4a-98e5-4610-8aa4-868a64a66933',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_SAM',
                'group_id' => '077506ed-9a72-4661-b123-2ec5d4a59222',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_SOC',
                'group_id' => '7fdd008d-65a3-4695-a650-40578dba5a10',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_Strömbacka',
                'group_id' => '15ce5bbc-9978-4609-96e5-f1f817d3e55a',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
            [
                'name' => 'Webb_Skribent_UBF',
                'group_id' => 'fbebd62c-4445-406c-96f6-545934867446',
                'role' => 'editor',
                'add_to_user_group' => true,
            ],
        ];
    }

    private function denyLogin(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::error($message);
        }

        wp_die(
            esc_html($message),
            esc_html__('Access denied', 'pitea-customisation'),
            ['response' => 403]
        );
    }
}
