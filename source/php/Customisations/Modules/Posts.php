<?php
namespace PiteaCustomisation\Customisations\Modules;

class Posts
{
    /**
     * Initialize Posts customisations
     */
    public function __construct()
    {
        // Customisations for Posts can be added here in the future
        add_filter('Modularity/Module/Posts/ArchiveLink/Icon', function() {
            return 'fa-solid fa-arrow-right';
        }, 10, 1);
    }
}