<?php

namespace PiteaCustomisation\Customisations;

/**
 * Fills an empty browser title.
 *
 * Municipio prints wp_title() from BaseController::getPageTitle(). On the
 * static front page that call is empty, while wp_get_document_title() still
 * returns the document title. Other views already get a title from wp_title().
 */
class DocumentTitle
{
    public function __construct()
    {
        add_filter('Municipio/postTitle', [$this, 'fillEmptyPageTitle']);
    }

    /**
     * @param string $title Title from wp_title(), including its separator.
     */
    public function fillEmptyPageTitle(string $title): string
    {
        if (trim($title) !== '') {
            return $title;
        }

        $documentTitle = wp_get_document_title();

        return is_string($documentTitle) && $documentTitle !== '' ? $documentTitle : $title;
    }
}
