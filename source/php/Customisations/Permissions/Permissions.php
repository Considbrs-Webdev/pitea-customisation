<?php

declare(strict_types=1);

namespace PiteaCustomisation\Customisations\Permissions;

class Permissions
{
    public function __construct()
    {
        new ReadOnlyPermissions();
        new PageTreeOwnership();
    }
}
