<?php

namespace PiteaCustomisation\Customisations;

/**
 * Class Typesense
 *
 * Loader for all Typesense Search customisations specific to the Piteå
 * WordPress installation. Each concern lives in its own class under the
 * Typesense/ sub-folder; this class simply bootstraps them.
 */
class Typesense
{
    public function __construct()
    {
        new Typesense\SearchTemplates();
        new Typesense\NestedPagesSync();
    }
}