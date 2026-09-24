<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\MenuBuilder;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Syriable\Filament\Plugins\MenuBuilder\Data\ResolvedMenuItem;
use Syriable\Filament\Plugins\MenuBuilder\Enums\RenderAs;
use Syriable\Filament\Plugins\MenuBuilder\Support\MenuRepository;
use Syriable\Filament\Plugins\MenuBuilder\Support\UrlParameters;
use Syriable\Filament\Plugins\MenuBuilder\Support\VisibilityResolver;
use Syriable\Filament\Plugins\MenuBuilder\Tree\MenuTree;

/**
 * Turns the published tree of a placement into frontend-ready items.
 *
 * Inactive and invisible items are removed together with their subtree,
 * labels (translated into the current locale) and URLs are resolved, linked records are loaded with one query
 * per item type, and the current page is marked. Runs in O(n).
 */
final readonly class MenuBuilder
{
    public function __construct(
        private MenuRegistry $registry,
        private MenuRepository $repository,
        private VisibilityResolver $visibility,
        private AuthFactory $auth,
        private UrlGenerator $url,
        private Translator $translator,
        private UrlParameters $parameters,
    ) {}

    /**
     * @param  Authenticatable|null  $user  Defaults to the authenticated user of the default guard.
     * @param  string|null  $currentUrl  Defaults to the current request URL.
     * @param  string|null  $locale  Language of the labels; defaults to the application locale.
     * @return Collection<int, ResolvedMenuItem>
     */
    public function build(string $placement, ?Authenticatable $user = null, ?string $currentUrl = null, ?string $locale = null): Collection
    {
        $this->registry->placement($placement);

        $tree = $this->repository->published($placement);
        $user ??= $this->auth->guard()->user();
        $current = $this->normalizeUrl($currentUrl ?? $this->url->current());

        $keys = $this->visibleKeys($tree, $user);
        $records = $this->loadRecords($tree, $keys);
        $locale ??= $this->translator->getLocale();

        // URL placeholders such as {user} resolve for the user the menu is built for.
        return collect($this->parameters->forUser(
            $user,
            fn (): array => $this->resolveChildren($tree, null, $keys, $records, $current, $locale, 1),
        ));
    }

    /**
     * Keys of all active, visible items of a known type whose ancestors are
     * visible too.
     *
     * @return array<string, true>
     */
    private function visibleKeys(MenuTree $tree, ?Authenticatable $user): array
    {
        $visible = [];
        $stack = $tree->roots();

        while ($stack !== []) {
            $key = array_pop($stack);
            $node = $tree->get($key);

            if (! $node->isActive || ! $this->registry->hasItemType($node->type) || ! $this->visibility->isVisible($node, $user)) {
                continue;
            }

            $visible[$key] = true;
            array_push($stack, ...$tree->childrenOf($key));
        }

        return $visible;
    }

    /**
     * @param  array<string, true>  $keys
     * @return array<string, array<int|string, Model>> Records by item type and record key.
     */
    private function loadRecords(MenuTree $tree, array $keys): array
    {
        $recordKeys = [];

        foreach ($keys as $key => $visible) {
            $node = $tree->get($key);
            $type = $this->registry->itemType($node->type);

            if ($type->isModelBacked() && ($recordKey = $type->getRecordKey($node)) !== null) {
                $recordKeys[$node->type][] = $recordKey;
            }
        }

        $records = [];

        foreach ($recordKeys as $type => $typeKeys) {
            $records[$type] = $this->registry->itemType($type)->findRecords($typeKeys);
        }

        return $records;
    }

    /**
     * @param  array<string, true>  $visible
     * @param  array<string, array<int|string, Model>>  $records
     * @return list<ResolvedMenuItem>
     */
    private function resolveChildren(MenuTree $tree, ?string $parentKey, array $visible, array $records, ?string $current, string $locale, int $depth): array
    {
        $items = [];

        foreach ($tree->childrenOf($parentKey) as $key) {
            if (! isset($visible[$key])) {
                continue;
            }

            $node = $tree->get($key);
            $type = $this->registry->itemType($node->type);
            $record = null;

            if ($type->isModelBacked()) {
                $recordKey = $type->getRecordKey($node);
                $record = $recordKey === null ? null : ($records[$node->type][$recordKey] ?? null);

                if ($record === null) {
                    continue;
                }
            }

            $url = $type->resolveUrl($node, $record);
            $renderAs = $node->renderAs() ?? $type->getRenderAs();

            // A link without a URL (missing route, deleted record, ...) is left out.
            if ($renderAs === RenderAs::Link && $url === null) {
                continue;
            }

            $children = $this->resolveChildren($tree, $key, $visible, $records, $current, $locale, $depth + 1);
            $isCurrent = $current !== null && $url !== null && $this->normalizeUrl($url) === $current;

            $items[] = new ResolvedMenuItem(
                id: (int) $node->id,
                type: $node->type,
                label: $node->translatedLabel($locale) ?? $type->resolveLabel($node, $record),
                url: $url,
                depth: $depth,
                icon: $node->icon,
                color: $node->color,
                badge: $node->badge,
                badgeColor: $node->badgeColor,
                openInNewTab: (bool) ($node->data['new_tab'] ?? false),
                isCurrent: $isCurrent,
                isActiveTrail: array_any($children, static fn (ResolvedMenuItem $child): bool => $child->isActive()),
                data: $node->data,
                children: $children,
                renderAs: $renderAs,
                attributes: $node->htmlAttributes(),
                attributeTarget: $node->attributeTarget(),
                badgePosition: $node->badgePosition(),
                textStyle: $node->textStyle(),
                screens: $node->screens(),
            );
        }

        return $items;
    }

    /**
     * Normalizes http(s) URLs and paths for comparison. Other URLs (anchors,
     * mailto:, tel:, ...) never match the current page.
     */
    private function normalizeUrl(string $url): ?string
    {
        if (! str_starts_with($url, '/') && ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $url = strtok($this->url->to($url), '?#');

        return $url === false ? null : rtrim(strtolower($url), '/');
    }
}
