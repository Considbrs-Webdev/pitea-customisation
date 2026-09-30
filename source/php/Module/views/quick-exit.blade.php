@php
    $showSticky = !$isPreview && in_array($display, ['sticky', 'both'], true);
    $showInline = $isPreview || in_array($display, ['inline', 'both'], true);
@endphp

@if ($showInline)
    @include('quick-exit-panel', ['variant' => 'inline'])
@endif

@if ($showSticky)
    @include('quick-exit-panel', ['variant' => 'sticky'])
@endif
