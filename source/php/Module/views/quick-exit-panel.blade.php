<div
    class="mod-quick-exit mod-quick-exit--{{ $variant }} u-print-display--none{{ $isPreview ? ' mod-quick-exit--preview' : '' }}"
    @if ($variant === 'sticky') data-quick-exit-sticky @endif
    @if ($shortcut && !$isPreview) data-quick-exit-shortcut data-quick-exit-messages="{{ json_encode($messages) }}" @endif
>
    @if ($isPreview && !empty($settingsUrl))
        <p class="mod-quick-exit__editor-note">
            {{ $editorHelp }}
            <a href="{{ $settingsUrl }}" target="_blank" rel="noopener noreferrer">{{ $editorLinkText }}</a>
        </p>
    @endif

    @button([
        'text' => $label,
        'style' => 'filled',
        'color' => 'primary',
        'href' => $isPreview ? false : $url,
        'size' => 'md',
        'classList' => ['mod-quick-exit__button'],
        'attributeList' => array_filter([
            'data-quick-exit' => $isPreview ? null : '',
            'rel' => 'nofollow noreferrer',
            'aria-label' => $label,
        ], static fn ($value) => $value !== null),
    ])
    @endbutton

    @if ($shortcut && !$isPreview)
        <span class="mod-quick-exit__dots" aria-hidden="true"><i></i><i></i><i></i></span>
    @endif

    <details class="mod-quick-exit__details">
        <summary class="mod-quick-exit__summary">
            <span class="mod-quick-exit__summary-text">{{ $heading }}</span>
        </summary>
        <div class="mod-quick-exit__body">
            <p class="mod-quick-exit__text">{{ $text }}</p>
            @if (!empty($readMoreUrl))
                <a class="mod-quick-exit__more" href="{{ $readMoreUrl }}">{{ $readMoreText }}</a>
            @endif
        </div>
    </details>
</div>
