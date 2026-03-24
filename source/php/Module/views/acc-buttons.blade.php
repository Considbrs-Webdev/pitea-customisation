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
    <nav class="mod-acc-buttons" aria-label="{{ __('Accessibility', 'municipio') }}">
        <ul class="mod-acc-buttons__list nav-accessibility unlist u-print-display--none" role="menubar">
            @foreach ($items as $item)
                @if (!empty($item['dropdown']))
                    @dropdown([
                        'popup' => 'click',
                        'componentElement' => 'li',
                    ])
                        @link([
                            'href' => null,
                            'attributeList' => [
                                'aria-label' => $item['label'] ?? '',
                            ],
                            'classList' => [
                                'js-dropdown-button',
                            ],
                        ])
                            @icon([
                                'icon' => $item['button']['icon'],
                                'size' => 'md',
                            ])
                            @endicon
                            {{ $item['button']['text'] ?? __('Expand', 'municipio') }}
                        @endlink
                        @slot('list')
                            @foreach ($item['dropdown'] as $dropdownItem)
                                <li role="menuitem">
                                    @button([
                                        'text' => $dropdownItem['text'] ?? false,
                                        'style' => $dropdownItem['style'] ?? 'outlined',
                                        'color' => $dropdownItem['color'] ?? 'primary',
                                        'href' => $dropdownItem['href'] ?? false,
                                        'icon' => $dropdownItem['icon'] ?? null,
                                        'size' => $dropdownItem['iconSize'] ?? 'sm',
                                        'attributeList' => array_merge($dropdownItem['attributeList'] ?? [], [
                                            'onClick' => $dropdownItem['script'] ?? '',
                                            'aria-label' => $dropdownItem['label'] ?? '',
                                        ])
                                    ])
                                    @endbutton
                                </li>
                            @endforeach
                        @endslot
                    @enddropdown
                @else
                    <li role="menuitem">
                        @button([
                            'text' => $item['text'] ?? false,
                            'style' => $item['style'] ?? 'outlined',
                            'color' => $item['color'] ?? 'primary',
                            'href' => $item['href'] ?? false,
                            'icon' => $item['icon'] ?? null,
                            'size' => $item['iconSize'] ?? 'sm',
                            'attributeList' => array_merge($item['attributeList'] ?? [], [
                                'onClick' => $item['script'] ?? '',
                                'aria-label' => $item['label'] ?? '',
                            ])
                        ])
                        @endbutton
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
@endif
