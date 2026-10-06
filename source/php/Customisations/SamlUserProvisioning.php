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

    /**
     * @var list<array{group_id: string, name: string, role: string, add_to_user_group: bool}>|null
     */
    private ?array $allowedGroups = null;

    public function __construct()
    {
        if ($this->getAllowedGroups() === []) {
            return;
        }

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
        if ($this->allowedGroups !== null) {
            return $this->allowedGroups;
        }

        $this->allowedGroups = [];

        if (!defined('PITEA_SAML_GROUPS') || !is_array(PITEA_SAML_GROUPS)) {
            return $this->allowedGroups;
        }

        foreach (PITEA_SAML_GROUPS as $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupId = strtolower(trim((string) ($group['group_id'] ?? '')));
            $name = trim((string) ($group['name'] ?? ''));
            $role = sanitize_key((string) ($group['role'] ?? ''));

            if ($groupId === '' || $name === '' || $role === '') {
                continue;
            }

            $this->allowedGroups[] = [
                'group_id' => $groupId,
                'name' => $name,
                'role' => $role,
                'add_to_user_group' => (bool) ($group['add_to_user_group'] ?? false),
            ];
        }

        return $this->allowedGroups;
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
