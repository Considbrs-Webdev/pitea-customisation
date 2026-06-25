<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

/**
 * Site-specific behaviour for the Scheduled Page Reviews plugin.
 */
class ScheduledPageReviews
{
    public function __construct()
    {
        add_filter('scheduled_page_reviews/can_view_site_overview', [$this, 'limitOverviewToAdministrators'], 10, 2);
        add_filter('scheduled_page_reviews/can_manage_settings', [$this, 'limitOverviewToAdministrators'], 10, 2);
    }

    /**
     * Only true WP administrators (and super admins) get site-wide review overview
     * and access to the Scheduled Page Reviews settings SPA.
     *
     * Users with manage_options who are not administrators — for example content
     * coordinators — still see pages where they are configured as recipients.
     */
    public function limitOverviewToAdministrators(bool $can, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (is_multisite() && is_super_admin($userId)) {
            return true;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return false;
        }

        return in_array('administrator', (array) $user->roles, true);
    }
}
