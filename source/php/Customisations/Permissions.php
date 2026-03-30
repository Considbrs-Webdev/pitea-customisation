<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations;

class Permissions
{
    public function __construct()
    {
        new Permissions\ReadOnlyPermissions();
        new Permissions\PageTreeOwnership();
    }
}
