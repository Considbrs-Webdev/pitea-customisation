<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use WP_CLI;

class SamlUserProvisioning
{
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

    public function __construct()
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('pitea-saml replay-attrs', [$this, 'replayAttributesCommand']);
            WP_CLI::add_command('pitea-saml run-miniorange-flow', [$this, 'runMiniOrangeFlowCommand']);
        }
    }

    /**
     * Replay captured AzureAD SAML attributes through the miniOrange custom action.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Print the captured attributes without firing the action.
     *
     * [--yes]
     * : Skip confirmation before firing the action.
     *
     * ## EXAMPLES
     *
     *     wp pitea-saml replay-attrs --dry-run
     *     wp pitea-saml replay-attrs --yes
     *
     * @param array $args Positional WP-CLI arguments.
     * @param array $assocArgs Associative WP-CLI arguments.
     */
    public function replayAttributesCommand(array $args, array $assocArgs): void
    {
        $attrs = $this->getCapturedAzureAdAttributes();
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
     * [--yes]
     * : Skip confirmation before running the flow.
     *
     * ## EXAMPLES
     *
     *     wp pitea-saml run-miniorange-flow --cleanup --yes
     *
     * @param array $args Positional WP-CLI arguments.
     * @param array $assocArgs Associative WP-CLI arguments.
     */
    public function runMiniOrangeFlowCommand(array $args, array $assocArgs): void
    {
        if (!class_exists('Mo_SAML_Login_Validate')) {
            WP_CLI::error('Mo_SAML_Login_Validate is not available. Is the miniOrange SAML plugin active?');
        }

        $attrs = $this->getCapturedAzureAdAttributes();
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
