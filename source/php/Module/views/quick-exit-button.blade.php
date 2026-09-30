@button([
    'text' => $label,
    'style' => 'filled',
    'color' => 'primary',
    'href' => $isPreview ? false : $url,
    'icon' => 'fa-solid fa-arrow-right',
    'size' => 'md',
    'reversePositions' => true,
    'attributeList' => array_filter([
        'data-quick-exit' => $isPreview ? null : '',
        'data-quick-exit-escape' => $escapeEnabled ? '1' : '0',
        'rel' => 'noopener noreferrer',
    ], static fn ($value) => $value !== null),
])
@endbutton
