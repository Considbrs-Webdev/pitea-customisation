{{--
    Nyhet single metadata: nyhetskategori tags only.
--}}
<div class="c-news-single__meta">
    <div class="c-news-single__tags-wrap" aria-label="{{ __('Nyhetskategori', 'pitea-customisation') }}">
        @tags([
            'compress' => 4,
            'tags' => $newsSingleTags,
            'format' => false,
            'classList' => ['c-news-single__tags'],
        ])
        @endtags
    </div>
</div>
