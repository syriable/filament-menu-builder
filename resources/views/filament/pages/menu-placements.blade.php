<x-filament-panels::page>
    @php($placements = $this->getPlacements())

    @if ($placements === [])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('menu-builder::menu-builder.placements.empty') }}
            </p>
        </x-filament::section>
    @else
        <div class="mb-placements">
            @foreach ($placements as $entry)
                <a href="{{ $entry['url'] }}" class="mb-placement-card" wire:key="placement-{{ $entry['placement']->getKey() }}">
                    <div class="mb-placement-card-header">
                        @if ($icon = $entry['placement']->getIcon())
                            <x-filament::icon :icon="$icon" class="mb-placement-card-icon" />
                        @endif

                        <span class="mb-placement-card-title">{{ $entry['placement']->getLabel() }}</span>
                    </div>

                    @if ($description = $entry['placement']->getDescription())
                        <p class="mb-placement-card-description">{{ $description }}</p>
                    @endif

                    <div class="mb-placement-card-footer">
                        <span>{{ trans_choice('menu-builder::menu-builder.placements.items', $entry['count'], ['count' => $entry['count']]) }}</span>
                        <span class="mb-placement-card-link">{{ __('menu-builder::menu-builder.placements.manage') }} &rarr;</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
