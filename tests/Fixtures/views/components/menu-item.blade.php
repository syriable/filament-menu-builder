@props(['item', 'level' => 1, 'headingTag' => 'span'])

<em class="custom-item" data-level="{{ $level }}">{{ $item->label }}</em>
