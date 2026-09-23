@foreach ($keys as $key)
    @php
        $node = $tree->get($key);
        $type = $view['types'][$node->type] ?? null;
        $children = $tree->childrenOf($key);
        $canAddChild = $view['can']['create']
            && ($view['canHaveChildren'][$node->type] ?? false)
            && ($view['maxDepth'] === null || $depth < $view['maxDepth']);
        $visibility = $view['visibilities'][$node->visibility] ?? null;
    @endphp

    <li
        class="mb-node"
        role="treeitem"
        aria-level="{{ $depth }}"
        data-key="{{ $key }}"
        wire:key="menu-node-{{ $key }}"
    >
        <div class="mb-row">
            @if ($view['can']['reorder'])
                <button
                    type="button"
                    class="mb-handle"
                    title="{{ __('menu-builder::menu-builder.tree.drag') }}"
                    aria-label="{{ __('menu-builder::menu-builder.tree.drag') }}"
                >
                    <x-filament::icon icon="heroicon-m-bars-3" class="mb-icon-sm" />
                </button>
            @endif

            @if ($children !== [])
                <button
                    type="button"
                    class="mb-toggle"
                    aria-label="{{ __('menu-builder::menu-builder.tree.toggle') }}"
                >
                    <x-filament::icon icon="heroicon-m-chevron-down" class="mb-icon-sm" />
                </button>
            @else
                <span class="mb-toggle-spacer"></span>
            @endif

            @if ($icon = $node->icon ?? $type?->getIcon())
                <x-filament::icon :icon="$icon" class="mb-node-icon" />
            @endif

            <span @class(['mb-label', 'mb-label-inactive' => ! $node->isActive])>{{ $view['labels'][$key] }}</span>

            <span class="mb-type">{{ $type?->getLabel() ?? $node->type }}</span>

            @if ($node->badge)
                <x-filament::badge size="sm" :color="$node->badgeColor ?? 'gray'">{{ $node->badge }}</x-filament::badge>
            @endif

            @if ($node->renderAs() === \Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs::Button)
                <x-filament::badge size="sm" color="primary" icon="heroicon-m-cursor-arrow-rays">{{ __('menu-builder::menu-builder.render_as.button') }}</x-filament::badge>
            @endif

            @if ($node->isNew())
                <x-filament::badge size="sm" color="info">{{ __('menu-builder::menu-builder.tree.new') }}</x-filament::badge>
            @endif

            @if (! $node->isActive)
                <x-filament::badge size="sm" color="gray">{{ __('menu-builder::menu-builder.tree.inactive') }}</x-filament::badge>
            @endif

            @if ($node->visibility !== 'everyone')
                <x-filament::badge size="sm" color="warning" icon="heroicon-m-eye">{{ $visibility?->getLabel() ?? $node->visibility }}</x-filament::badge>
            @endif

            <span class="mb-actions">
                @if ($canAddChild)
                    <x-filament::icon-button
                        icon="heroicon-m-plus"
                        size="sm"
                        color="gray"
                        :label="__('menu-builder::menu-builder.actions.create_child')"
                        :title="__('menu-builder::menu-builder.actions.create_child')"
                        wire:click="mountAction('createItem', {{ \Illuminate\Support\Js::from(['parent' => $key]) }})"
                    />
                @endif

                @if ($view['can']['update'])
                    <x-filament::icon-button
                        icon="heroicon-m-pencil-square"
                        size="sm"
                        color="gray"
                        :label="__('menu-builder::menu-builder.actions.edit')"
                        :title="__('menu-builder::menu-builder.actions.edit')"
                        wire:click="mountAction('editItem', {{ \Illuminate\Support\Js::from(['key' => $key]) }})"
                    />
                @endif

                @if ($view['can']['delete'])
                    <x-filament::icon-button
                        icon="heroicon-m-trash"
                        size="sm"
                        color="danger"
                        :label="__('menu-builder::menu-builder.actions.delete')"
                        :title="__('menu-builder::menu-builder.actions.delete')"
                        wire:click="mountAction('deleteItem', {{ \Illuminate\Support\Js::from(['key' => $key]) }})"
                    />
                @endif
            </span>
        </div>

        @if ($children !== [])
            <ul class="mb-children" role="group">
                @include('menu-builder::filament.tree-items', ['keys' => $children, 'depth' => $depth + 1])
            </ul>
        @endif
    </li>
@endforeach
