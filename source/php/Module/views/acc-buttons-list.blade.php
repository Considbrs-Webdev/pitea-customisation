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
                    <li>
                        @button([
                            'text' => $dropdownItem['text'] ?? false,
                            'style' => $dropdownItem['style'] ?? 'outlined',
                            'color' => $dropdownItem['color'] ?? 'primary',
                            'href' => $dropdownItem['href'] ?? false,
                            'icon' => $dropdownItem['icon'] ?? null,
                            'size' => $dropdownItem['iconSize'] ?? 'sm',
                            'reversePositions' => $dropdownItem['reversePositions'] ?? false,
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
        <li>
            @button([
                'text' => $item['text'] ?? false,
                'style' => $item['style'] ?? 'outlined',
                'color' => $item['color'] ?? 'primary',
                'href' => $item['href'] ?? false,
                'icon' => $item['icon'] ?? null,
                'size' => $item['iconSize'] ?? 'sm',
                'reversePositions' => $item['reversePositions'] ?? false,
                'attributeList' => array_merge($item['attributeList'] ?? [], [
                    'onClick' => $item['script'] ?? '',
                    'aria-label' => $item['label'] ?? '',
                ])
            ])
            @endbutton
        </li>
    @endif
@endforeach
