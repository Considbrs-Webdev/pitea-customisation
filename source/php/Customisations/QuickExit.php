<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\AcfFields\QuickExitFields;

/**
 * Boots the ACF fields for the quick-exit Modularity module.
 */
class QuickExit
{
    public function __construct()
    {
        new QuickExitFields();
    }
}
