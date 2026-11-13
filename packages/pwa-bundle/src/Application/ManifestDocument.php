<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Application;

use Phil\PwaBundle\Application\Contract\AssetUrls;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\IconPurpose;
use Phil\PwaBundle\Domain\ValueObject\Icon;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\Shortcut;

/**
 * The manifest as the JSON browsers read: the members of the specification, those not given left out, the source of
 * each icon given by AssetUrls when it is the logical path of an asset.
 */
final readonly class ManifestDocument
{
    public function __construct(
        private AssetUrls $assets,
    ) {
    }

    public function render(Manifest $manifest): string
    {
        return json_encode(array_filter([
            'name' => $manifest->name,
            'short_name' => $manifest->shortName,
            'description' => $manifest->description,
            'id' => $manifest->id,
            'start_url' => $manifest->startUrl,
            'scope' => $manifest->scope,
            'display' => $manifest->display->value,
            'display_override' => array_map(static fn (DisplayMode $mode): string => $mode->value, $manifest->displayOverride),
            'orientation' => $manifest->orientation?->value,
            'theme_color' => $manifest->themeColor,
            'background_color' => $manifest->backgroundColor,
            'lang' => $manifest->lang,
            'dir' => $manifest->dir,
            'icons' => array_map($this->icon(...), $manifest->icons),
            'shortcuts' => array_map(static fn (Shortcut $shortcut): array => array_filter([
                'name' => $shortcut->name,
                'short_name' => $shortcut->shortName,
                'description' => $shortcut->description,
                'url' => $shortcut->url,
            ], static fn (?string $value): bool => null !== $value), $manifest->shortcuts),
            'categories' => $manifest->categories,
        ], static fn (mixed $value): bool => null !== $value && [] !== $value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT)."\n";
    }

    /**
     * @return array<string, string>
     */
    private function icon(Icon $icon): array
    {
        return array_filter([
            'src' => str_starts_with($icon->src, '/') ? $icon->src : $this->assets->url($icon->src),
            'sizes' => $icon->sizes,
            'type' => $icon->type,
            'purpose' => implode(' ', array_map(static fn (IconPurpose $purpose): string => $purpose->value, $icon->purposes)),
        ], static fn (?string $value): bool => null !== $value);
    }
}
