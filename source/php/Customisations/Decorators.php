<?php

namespace PiteaCustomisation\Customisations;

use PiteaCustomisation\Decorators\NewsDateDecorator;
use Municipio\PostObject\PostObjectInterface;

class Decorators {
    /**
     * Initialize decorators and setup
     */
    public function __construct() {
        // Decorate the PostObject so getTitle() returns empty, hiding the H1 in templates
        add_filter('Municipio/DecoratePostObject', function (PostObjectInterface $postObject) {
            if ($postObject->getPostType() !== 'news') {
                return $postObject;
            }

            $postObject = new NewsDateDecorator($postObject);
            
            return $postObject;
        }, 20);
    }
}
