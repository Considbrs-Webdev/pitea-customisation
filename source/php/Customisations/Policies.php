<?php

namespace PiteaCustomisation\Customisations;

class Policies
{
    /**
     * Custom CSP values to merge into each directive.
     * Add entries here for values that cannot be configured via the admin.
     *
     * @var array<string, string[]>
     */
    private array $customPolicies = [
        'connect-src' => ['data:'],
        'img-src'     => ['data:'],
    ];

    public function __construct()
    {
        add_filter('WpSecurity/Csp', [$this, 'applyCustomPolicies'], 10, 1);
    }

    /**
     * Merges custom CSP values into the resolved policy array.
     *
     * @param array $cspPolicies Associative array of directive => values[].
     * @return array
     */
    public function applyCustomPolicies(array $cspPolicies): array
    {
        foreach ($this->customPolicies as $directive => $values) {
            if (!isset($cspPolicies[$directive])) {
                $cspPolicies[$directive] = [];
            }

            // Remove 'none' placeholder if real values are being added
            $cspPolicies[$directive] = array_filter(
                $cspPolicies[$directive],
                fn($v) => $v !== "'none'"
            );

            foreach ($values as $value) {
                if (!in_array($value, $cspPolicies[$directive], true)) {
                    $cspPolicies[$directive][] = $value;
                }
            }
        }

        return $cspPolicies;
    }
}