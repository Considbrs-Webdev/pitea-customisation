@php
    $showSticky = !$isPreview && in_array($display, ['sticky', 'both'], true);
    $showInline = $isPreview || in_array($display, ['inline', 'both'], true);
@endphp

@if ($showInline)
    <div class="mod-quick-exit mod-quick-exit--inline u-print-display--none">
        @include('quick-exit-button')
        @if (!empty($infoUrl))
            <p class="mod-quick-exit__info"><a class="c-link" href="{{ $infoUrl }}">{{ $infoText }}</a></p>
        @endif
    </div>
@endif

@if ($showSticky)
    <div class="mod-quick-exit mod-quick-exit--sticky u-print-display--none" data-quick-exit-sticky>
        @include('quick-exit-button')
    </div>
@endif
