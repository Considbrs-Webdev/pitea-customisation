<?php

namespace PiteaCustomisation\Customisations;

class News
{
    public function __construct()
    {
        // Remove image from news item when used in posts module
        add_filter('ComponentLibrary/Component/NewsItem/Data', [$this, 'removeImageFromNewsItem']);
        add_filter('ComponentLibrary/Component/Tags/Data', [$this, 'removeTagLabel']);
    }

    /**
     * Remove image from news item when used in posts module
     *
     * @param array $data
     * @return array
     */
    public function removeImageFromNewsItem(array $data): array
    {  
        $contexts = [
            'archive.list.news-item',
            'module.posts.news-item',
        ];

        $dataContexts = (array) ($data['context'] ?? []);
        if (!empty(array_intersect($contexts, $dataContexts))) {
            $data['image'] = null;
            $data['hasPlaceholderImage'] = false;
        }

        return $data;
    }

    /**
     * Remove tag label from tags component
     *
     * @param array $data
     * @return array
     */
    public function removeTagLabel(array $data): array
    {
        $data['beforeLabel'] = '';

        return $data;
    }
}
