@if (!$hideTitle && !empty($postTitle))
    @typography([
        'element' => 'h2',
        'variant' => 'h2',
        'classList' => ['mod-acc-buttons__title', 'u-margin__top--0', 'u-margin__bottom--2'],
    ])
        {{ $postTitle }}
    @endtypography
@endif

@if (!empty($items) && is_array($items))
    <nav class="mod-acc-buttons {{ $automaticMobileInsertion ? 'mod-acc-buttons--hide-mobile' : '' }}" aria-label="{{ __('Accessibility', 'municipio') }}">
        <ul class="mod-acc-buttons__list nav-accessibility unlist u-print-display--none">
            @include('acc-buttons-list', ['items' => $items])
        </ul>
    </nav>

    @if ($automaticMobileInsertion)
        {{-- Moved to the top of #main-content on mobile by acc-buttons.js, since sidebars stack below the main content there. --}}
        <nav class="mod-acc-buttons mod-acc-buttons--hide-desktop" aria-label="{{ __('Accessibility', 'municipio') }}" data-acc-buttons-mobile-root="{{ $mobileId }}">
            <ul class="mod-acc-buttons__list nav-accessibility unlist u-print-display--none">
                @include('acc-buttons-list', ['items' => $items])
            </ul>
        </nav>
    @endif
@endif
