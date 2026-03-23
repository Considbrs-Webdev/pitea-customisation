<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations\ColorPicker;

/**
 * Resolves user-group-based palette restrictions stored in plugin settings.
 *
 * Merge policy for users in multiple groups: union of allowed palette groups;
 * custom color is allowed if any matching rule allows it.
 */
final class UserGroupPaletteAccess
{
    public const OPTION_KEY = 'pitea_customisation_color_picker_group_rules';

    /**
     * @return list<array{user_group_id: int, allowed_palette_groups: list<string>, allow_custom: bool}>
     */
    public static function getDecodedRules(): array
    {
        $raw = get_option(self::OPTION_KEY, '[]');
        if (!is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $gid = isset($row['user_group_id']) ? absint($row['user_group_id']) : 0;
            if ($gid === 0) {
                continue;
            }
            $groups = $row['allowed_palette_groups'] ?? [];
            if (!is_array($groups)) {
                $groups = [];
            }
            $cleanGroups = [];
            foreach ($groups as $g) {
                if (is_string($g) && $g !== '') {
                    $cleanGroups[] = $g;
                }
            }
            $out[] = [
                'user_group_id'          => $gid,
                'allowed_palette_groups' => $cleanGroups,
                'allow_custom'           => !empty($row['allow_custom']),
            ];
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function getUserGroupTermIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $terms = wp_get_object_terms($userId, 'user_group', ['fields' => 'ids']);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return array_map('intval', $terms);
    }

    /**
     * Rules whose user_group_id is assigned to the user.
     *
     * @return list<array{user_group_id: int, allowed_palette_groups: list<string>, allow_custom: bool}>
     */
    public static function getMatchingRulesForUser(int $userId): array
    {
        $userTerms = self::getUserGroupTermIds($userId);
        if ($userTerms === []) {
            return [];
        }

        $userTermSet = array_flip($userTerms);
        $matching    = [];
        foreach (self::getDecodedRules() as $rule) {
            $gid = $rule['user_group_id'];
            if (isset($userTermSet[$gid])) {
                $matching[] = $rule;
            }
        }

        return $matching;
    }

    /**
     * null = no restriction (full palette). Non-null = allowed group heading names (union); may be empty.
     *
     * @return list<string>|null
     */
    public static function getEffectiveAllowedGroupNames(int $userId): ?array
    {
        if (self::getDecodedRules() === []) {
            return null;
        }

        $matching = self::getMatchingRulesForUser($userId);
        if ($matching === []) {
            return null;
        }

        $union = [];
        foreach ($matching as $rule) {
            foreach ($rule['allowed_palette_groups'] as $name) {
                $union[$name] = true;
            }
        }

        return array_keys($union);
    }

    public static function getEffectiveAllowCustom(int $userId, bool $fieldAllowsCustom): bool
    {
        if (!$fieldAllowsCustom) {
            return false;
        }

        $matching = self::getMatchingRulesForUser($userId);
        if ($matching === []) {
            return true;
        }

        foreach ($matching as $rule) {
            if ($rule['allow_custom']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<array{name: string, hex: string, var: string|null}>> $fullGroups
     * @return array<string, list<array{name: string, hex: string, var: string|null}>>
     */
    public static function filterGroupsForUser(array $fullGroups, int $userId): array
    {
        $allowed = self::getEffectiveAllowedGroupNames($userId);
        if ($allowed === null) {
            return $fullGroups;
        }

        $allowedSet = array_flip($allowed);
        $out        = [];
        foreach ($fullGroups as $name => $colors) {
            if (isset($allowedSet[$name])) {
                $out[$name] = $colors;
            }
        }

        return $out;
    }

    /**
     * @param array<string, list<array{name: string, hex: string, var: string|null}>> $groups
     * @return array<string, string> name => hex
     */
    public static function flattenGroups(array $groups): array
    {
        $flat = [];
        foreach ($groups as $colors) {
            foreach ($colors as $colorData) {
                $flat[$colorData['name']] = $colorData['hex'];
            }
        }

        return $flat;
    }
}
