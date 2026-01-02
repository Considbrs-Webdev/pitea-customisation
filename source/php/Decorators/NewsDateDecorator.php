<?php

namespace PiteaCustomisation\Decorators;

use Municipio\PostObject\PostObjectInterface;
use Municipio\PostObject\Decorators\AbstractPostObjectDecorator;

/**
 * Class NewsDateDecorator
 *
 * Decorator to modify news post objects to return date as archive date format.
 *
 * @package PiteaCustomisation\Decorators
 */
class NewsDateDecorator extends AbstractPostObjectDecorator implements PostObjectInterface {
    public function __construct(PostObjectInterface $postObject) {
        parent::__construct($postObject);
    }

    /**
     * Get the archive date format
     */
    public function getArchiveDateFormat(): string
    {
        $dateFormat = get_option('date_format');

        return $dateFormat ?? 'Y-m-d';
    }
}