<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use WP_CLI;

class SamlUserProvisioning
{
    private const USER_GROUP_TAXONOMY = 'user_group';
    private const DEFAULT_ONPREMISES_SAM_ACCOUNT_SUFFIX = ',OU=ANV,OU=LAB,OU=STH,OU=PK,OU=P,DC=it,DC=pitea,DC=se';
    private const CLAIM_TENANT_ID = 'http://schemas.microsoft.com/identity/claims/tenantid';
    private const CLAIM_OBJECT_IDENTIFIER = 'http://schemas.microsoft.com/identity/claims/objectidentifier';
    private const CLAIM_DISPLAY_NAME = 'http://schemas.microsoft.com/identity/claims/displayname';
    private const CLAIM_GROUPS = 'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups';
    private const CLAIM_IDENTITY_PROVIDER = 'http://schemas.microsoft.com/identity/claims/identityprovider';
    private const CLAIM_AUTHN_METHOD = 'http://schemas.microsoft.com/claims/authnmethodsreferences';
    private const CLAIM_GIVEN_NAME = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname';
    private const CLAIM_SURNAME = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname';
    private const CLAIM_EMAIL = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress';
    private const CLAIM_NAME = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name';
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

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('pitea-saml replay-attrs', [$this, 'replayAttributesCommand']);
            WP_CLI::add_command('pitea-saml run-miniorange-flow', [$this, 'runMiniOrangeFlowCommand']);
        }
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

    /**
     * Replay captured AzureAD SAML attributes through the miniOrange custom action.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Print the captured attributes without firing the action.
     *
     * [--display-name=<value>]
     * : Override http://schemas.microsoft.com/identity/claims/displayname.
     *
     * [--groups=<group-ids>]
     * : Comma-separated SAML group IDs.
     *
     * [--given-name=<value>]
     * : Override http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname.
     *
     * [--surname=<value>]
     * : Override http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname.
     *
     * [--email=<value>]
     * : Override http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress.
     *
     * [--account-name=<value>]
     * : Override only the CN part of user.onpremisessamaccountname.
     *
     * [--sam-account-dn=<value>]
     * : Override the full user.onpremisessamaccountname value.
     *
     * [--name-id=<value>]
     * : Override NameID.
     *
     * [--yes]
     * : Skip confirmation before firing the action.
     *
     * ## EXAMPLES
     *
     *     wp pitea-saml replay-attrs --dry-run
     *     wp pitea-saml replay-attrs --yes
     *     wp pitea-saml replay-attrs --dry-run --email=test@example.com --account-name=TEST01 --groups=82d935d3-a876-46f3-90a7-a62f710b50f8
     *
     * @param array $args Positional WP-CLI arguments.
     * @param array $assocArgs Associative WP-CLI arguments.
     */
    public function replayAttributesCommand(array $args, array $assocArgs): void
    {
        $attrs = $this->getCliAzureAdAttributes($assocArgs);
        $dryRun = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);

        WP_CLI::log('Captured AzureAD SAML attributes:');
        WP_CLI::log('  Email: ' . $attrs[self::CLAIM_EMAIL][0]);
        WP_CLI::log('  NameID: ' . $attrs['NameID'][0]);
        WP_CLI::log('  Display name: ' . $attrs[self::CLAIM_DISPLAY_NAME][0]);
        WP_CLI::log('  On-premises SAM account name: ' . $attrs[self::CLAIM_ONPREMISES_SAM_ACCOUNT_NAME][0]);
        WP_CLI::log('  Groups: ' . implode(', ', $attrs[self::CLAIM_GROUPS]));

        if ($dryRun) {
            WP_CLI::log('');
            WP_CLI::log(wp_json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            WP_CLI::success('Dry run complete. The mo_saml_user_attributes action was not fired.');
            return;
        }

        WP_CLI::confirm('Fire mo_saml_user_attributes with these attributes?', $assocArgs);

        do_action('mo_saml_user_attributes', $attrs);

        WP_CLI::success('Fired mo_saml_user_attributes with captured AzureAD attributes.');
    }

    /**
     * Replay captured AzureAD SAML attributes through miniOrange's actual mapping/login flow.
     *
     * ## OPTIONS
     *
     * [--cleanup]
     * : Delete the test user after the flow if this command created it.
     *
     * [--groups=<group-ids>]
     * : Comma-separated SAML group IDs to use instead of the captured fixture groups.
     *
     * [--display-name=<value>]
     * : Override http://schemas.microsoft.com/identity/claims/displayname.
     *
     * [--given-name=<value>]
     * : Override http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname.
     *
     * [--surname=<value>]
     * : Override http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname.
     *
     * [--email=<value>]
     * : Override http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress.
     *
     * [--account-name=<value>]
     * : Override only the CN part of user.onpremisessamaccountname.
     *
     * [--sam-account-dn=<value>]
     * : Override the full user.onpremisessamaccountname value.
     *
     * [--name-id=<value>]
     * : Override NameID.
     *
     * [--yes]
     * : Skip confirmation before running the flow.
     *
     * ## EXAMPLES
     *
     *     wp pitea-saml run-miniorange-flow --cleanup --yes
     *     wp pitea-saml run-miniorange-flow --groups=09d1a1d4-7902-40dd-8a1d-f385819b0c30 --cleanup --yes
     *     wp pitea-saml run-miniorange-flow --email=test@example.com --account-name=TEST01 --name-id=test@example.com --cleanup --yes
     *
     * @param array $args Positional WP-CLI arguments.
     * @param array $assocArgs Associative WP-CLI arguments.
     */
    public function runMiniOrangeFlowCommand(array $args, array $assocArgs): void
    {
        if (!class_exists('Mo_SAML_Login_Validate')) {
            WP_CLI::error('Mo_SAML_Login_Validate is not available. Is the miniOrange SAML plugin active?');
        }

        $attrs = $this->getCliAzureAdAttributes($assocArgs);
        $email = $attrs[self::CLAIM_EMAIL][0];
        $username = $attrs['NameID'][0];
        $cleanup = \WP_CLI\Utils\get_flag_value($assocArgs, 'cleanup', false);
        $existingUserId = $this->findUserId($email, $username);
        $observed = [
            'attributes_action_fired' => false,
            'group_action_fired' => false,
            'group_names' => [],
        ];

        WP_CLI::log('This will call Mo_SAML_Login_Validate::mo_saml_check_mapping() with captured AzureAD attributes.');
        WP_CLI::log('Email: ' . $email);
        WP_CLI::log('NameID / username: ' . $username);
        WP_CLI::log('Existing user: ' . ($existingUserId ? ('yes, ID ' . $existingUserId) : 'no'));
        WP_CLI::log('Cleanup new user afterward: ' . ($cleanup ? 'yes' : 'no'));
        WP_CLI::confirm('Run the actual miniOrange mapping/login flow?', $assocArgs);

        add_action(
            'mo_saml_user_attributes',
            static function () use (&$observed): void {
                $observed['attributes_action_fired'] = true;
            },
            PHP_INT_MAX
        );

        add_action(
            'mo_saml_user_group_name',
            static function ($userId, $groupNames) use (&$observed): void {
                $observed['group_action_fired'] = true;
                $observed['group_names'] = (array) $groupNames;
            },
            PHP_INT_MAX,
            2
        );

        register_shutdown_function(function () use ($email, $username, $existingUserId, $cleanup, &$observed): void {
            $finalUserId = $this->findUserId($email, $username);

            WP_CLI::log('');
            WP_CLI::log('miniOrange flow shutdown summary:');
            WP_CLI::log('  mo_saml_user_attributes fired: ' . ($observed['attributes_action_fired'] ? 'yes' : 'no'));
            WP_CLI::log('  mo_saml_user_group_name fired: ' . ($observed['group_action_fired'] ? 'yes' : 'no'));
            WP_CLI::log('  Group values passed onward: ' . ($observed['group_names'] ? implode(', ', $observed['group_names']) : 'none'));
            WP_CLI::log('  User after flow: ' . ($finalUserId ? ('ID ' . $finalUserId) : 'not found'));
            WP_CLI::log('  User roles after flow: ' . $this->formatUserRoles($finalUserId));
            WP_CLI::log('  User groups after flow: ' . $this->formatUserGroups($finalUserId));

            if ($cleanup && !$existingUserId && $finalUserId) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $deleted = is_multisite() ? wpmu_delete_user($finalUserId) : wp_delete_user($finalUserId);
                WP_CLI::log(
                    '  Cleanup: ' . ($deleted ? 'deleted' : 'could not delete') . ' newly created test user ID ' . $finalUserId
                );
            } elseif ($cleanup && $existingUserId) {
                WP_CLI::log('  Cleanup: skipped because the user existed before this command.');
            }
        });

        $validator = new \Mo_SAML_Login_Validate();
        $method = new \ReflectionMethod($validator, 'mo_saml_check_mapping');
        $method->setAccessible(true);
        $method->invoke($validator, $attrs, site_url('/'));
    }

    private function findUserId(string $email, string $username): int
    {
        $userId = email_exists($email);

        if (!$userId) {
            $userId = username_exists($username);
        }

        return $userId ? (int) $userId : 0;
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

    private function formatUserRoles(int $userId): string
    {
        if ($userId <= 0) {
            return 'none';
        }

        $user = get_userdata($userId);
        if (!$user) {
            return 'not found';
        }

        $roles = array_map('strval', (array) $user->roles);

        return $roles ? implode(', ', $roles) : 'none';
    }

    private function formatUserGroups(int $userId): string
    {
        if ($userId <= 0) {
            return 'none';
        }

        return $this->withUserGroupBlog(function () use ($userId): string {
            $terms = wp_get_object_terms($userId, self::USER_GROUP_TAXONOMY);

            if (is_wp_error($terms) || empty($terms)) {
                return 'none';
            }

            $labels = array_map(
                static fn($term): string => $term->name . ' (' . $term->slug . ')',
                $terms
            );

            return implode(', ', $labels);
        });
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
            WP_CLI::error($message);
        }

        wp_die(
            esc_html($message),
            esc_html__('Access denied', 'pitea-customisation'),
            ['response' => 403]
        );
    }

    private function getCliAzureAdAttributes(array $assocArgs): array
    {
        $attrs = $this->getCapturedAzureAdAttributes();

        $this->applyCliAttributeOverride($attrs, $assocArgs, 'display-name', self::CLAIM_DISPLAY_NAME);
        $this->applyCliAttributeOverride($attrs, $assocArgs, 'given-name', self::CLAIM_GIVEN_NAME);
        $this->applyCliAttributeOverride($attrs, $assocArgs, 'surname', self::CLAIM_SURNAME);
        $this->applyCliAttributeOverride($attrs, $assocArgs, 'email', self::CLAIM_EMAIL);
        $this->applyCliAttributeOverride($attrs, $assocArgs, 'name-id', 'NameID');

        if (array_key_exists('groups', $assocArgs)) {
            $attrs[self::CLAIM_GROUPS] = array_values(
                array_filter(
                    array_map('trim', explode(',', (string) $assocArgs['groups'])),
                    static fn(string $groupId): bool => $groupId !== ''
                )
            );
        }

        if (array_key_exists('account-name', $assocArgs)) {
            $attrs[self::CLAIM_ONPREMISES_SAM_ACCOUNT_NAME] = [
                'CN=' . trim((string) $assocArgs['account-name']) . self::DEFAULT_ONPREMISES_SAM_ACCOUNT_SUFFIX,
            ];
        }

        if (array_key_exists('sam-account-dn', $assocArgs)) {
            $attrs[self::CLAIM_ONPREMISES_SAM_ACCOUNT_NAME] = [
                trim((string) $assocArgs['sam-account-dn']),
            ];
        }

        return $attrs;
    }

    private function applyCliAttributeOverride(array &$attrs, array $assocArgs, string $option, string $claim): void
    {
        if (!array_key_exists($option, $assocArgs)) {
            return;
        }

        $attrs[$claim] = [
            trim((string) $assocArgs[$option]),
        ];
    }

    /**
     * Return the captured AzureAD attributes in the same shape miniOrange passes to mo_saml_user_attributes.
     */
    private function getCapturedAzureAdAttributes(): array
    {
        return [
            self::CLAIM_TENANT_ID => [
                '00d9152e-e7da-4edd-b1cf-9b706245cc51',
            ],
            self::CLAIM_OBJECT_IDENTIFIER => [
                'd27c976d-d5a6-4f52-8406-bd91942c2e11',
            ],
            self::CLAIM_DISPLAY_NAME => [
                'Moni Cipione',
            ],
            self::CLAIM_GROUPS => [
                'abb68757-56d0-4b80-ac14-d8c44539d450',
                '82d935d3-a876-46f3-90a7-a62f710b50f8',
            ],
            self::CLAIM_IDENTITY_PROVIDER => [
                'https://sts.windows.net/00d9152e-e7da-4edd-b1cf-9b706245cc51/',
            ],
            self::CLAIM_AUTHN_METHOD => [
                'http://schemas.microsoft.com/ws/2008/06/identity/authenticationmethod/password',
            ],
            self::CLAIM_GIVEN_NAME => [
                'Moni',
            ],
            self::CLAIM_SURNAME => [
                'Cipione',
            ],
            self::CLAIM_EMAIL => [
                'moni.cipione@pitea.se',
            ],
            self::CLAIM_NAME => [
                'labmun01@pitea.se',
            ],
            self::CLAIM_ONPREMISES_SAM_ACCOUNT_NAME => [
                'CN=LABMUN01,OU=ANV,OU=LAB,OU=STH,OU=PK,OU=P,DC=it,DC=pitea,DC=se',
            ],
            'NameID' => [
                'labmun01@pitea.se',
            ],
        ];
    }
}
