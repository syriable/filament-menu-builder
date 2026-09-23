@php
    use Filament\Support\Facades\FilamentAsset;

    $view = $this->getTreeViewData();
    $tree = $view['tree'];
@endphp

<x-filament-panels::page>
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('menu-builder-tree', 'syriable/filament-menu-builder') }}"
        x-data="menuBuilderTree({
            storageKey: @js('menu-builder.'.$this->placement.'.collapsed'),
            canReorder: @js($view['can']['reorder']),
            unsavedMessage: @js(__('menu-builder::menu-builder.status.leave_warning')),
        })"
        x-on:click="onClick($event)"
        x-on:pointerdown="onPointerDown($event)"
        x-on:dragstart="onDragStart($event)"
        x-on:dragend="onDragEnd($event)"
        x-on:dragover="onDragOver($event)"
        x-on:dragleave="onDragLeave($event)"
        x-on:drop="onDrop($event)"
        class="mb-tree-editor"
        data-dirty="{{ $view['isDirty'] ? 'true' : 'false' }}"
    >
        <style wire:ignore x-text="collapsedCss()"></style>

        <div class="mb-toolbar">
            <div class="mb-status" wire:key="menu-status-{{ $view['isDirty'] ? 'dirty' : 'saved' }}">
                @if ($view['isDirty'])
                    <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">
                        {{ __('menu-builder::menu-builder.status.unsaved') }}
                    </x-filament::badge>
                @else
                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                        {{ __('menu-builder::menu-builder.status.saved') }}
                    </x-filament::badge>
                @endif

                <span class="mb-muted">
                    {{ trans_choice('menu-builder::menu-builder.placements.items', count($tree), ['count' => count($tree)]) }}
                </span>
            </div>

            @if (count($tree) > 0)
                <div class="mb-toolbar-actions">
                    <x-filament::link tag="button" type="button" size="sm" color="gray" icon="heroicon-m-chevron-double-down" x-on:click="expandAllItems()">
                        {{ __('menu-builder::menu-builder.actions.expand_all') }}
                    </x-filament::link>
                    <x-filament::link tag="button" type="button" size="sm" color="gray" icon="heroicon-m-chevron-double-up" x-on:click="collapseAllItems()">
                        {{ __('menu-builder::menu-builder.actions.collapse_all') }}
                    </x-filament::link>
                </div>
            @endif
        </div>

        <x-filament::section>
            @if (count($tree) === 0)
                <div class="mb-empty">
                    <x-filament::icon icon="heroicon-o-bars-3" class="mb-empty-icon" />
                    <p>{{ __('menu-builder::menu-builder.tree.empty') }}</p>
                </div>
            @else
                <ul class="mb-tree" role="tree" aria-label="{{ $this->getTitle() }}">
                    @include('menu-builder::filament.tree-items', ['keys' => $tree->roots(), 'depth' => 1])
                </ul>

                @if ($view['can']['reorder'])
                    <div class="mb-root-drop" data-root-drop>
                        {{ __('menu-builder::menu-builder.tree.drop_root') }}
                    </div>
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
