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
        if (!in_array('module.posts.news-item', $data['context'])) {
            return $data;
        }

        $data['image'] = null;

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
