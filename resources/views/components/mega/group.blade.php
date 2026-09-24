{{--
    One group of a mega menu panel: its title (a heading, or a link for a
    clickable title) and its links. A group never breaks across columns.
    Levels below the links are not rendered; the preset placement allows
    three levels.
--}}
@props([
    'item',
    'itemComponent' => 'menu-builder::item',
    'headingTag' => 'span',
])

<div class="mb-mega-group">
    <div
        {{ $item->wrapperAttributes()->class([
            'mb-entry',
            'mb-mega-group-title',
            'mb-current' => $item->isCurrent,
            'mb-active-trail' => $item->isActiveTrail,
        ]) }}
    >
        <div class="mb-row">
            <x-dynamic-component
                :component="$itemComponent"
                :item="$item"
                :level="2"
                :heading-tag="$headingTag"
            />
        </div>
    </div>

    @if ($item->hasChildren())
        <ul class="mb-list mb-submenu mb-mega-links" data-level="3" role="list">
            @foreach ($item->children as $link)
                <li {{ $link->wrapperAttributes()->class(['mb-entry', 'mb-current' => $link->isCurrent]) }}>
                    <div class="mb-row">
                        <x-dynamic-component
                            :component="$itemComponent"
                            :item="$link"
                            :level="3"
                            :heading-tag="$headingTag"
                        />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
