<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Twig\Component;

use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Twig\ImageCandidates;
use Phil\MediaBundle\Infrastructure\Twig\ResponsiveImages;

/**
 * `<twig:PhilMedia:Img :media="post.cover" format="card" sizes="(min-width: 60em) 50vw, 100vw"/>`: an `<img>` of a
 * format in one file type, WebP by default, with its width and height, a `srcset` over the format's narrower widths,
 * `loading="lazy"` and `decoding="async"` — or, with `priority`, `loading="eager"` and `fetchpriority="high"`, and a
 * preload link. Its alternative text is the medium's, unless `alt` is given; any other attribute is printed as it is.
 * A medium that no longer exists prints nothing.
 */
final class Img
{
    public Media|MediaId|string|null $media = null;

    public string $format = 'original';

    public string $type = 'webp';

    public ?string $alt = null;

    public ?string $sizes = null;

    /** `lazy` or `eager`: `lazy` by default, `eager` with `priority`. */
    public ?string $loading = null;

    /** `async`, `sync` or `auto`: `async` by default, none written with `priority`. */
    public ?string $decoding = null;

    /**
     * Whether it is the main image above the fold: loaded eagerly, fetched first with `fetchpriority="high"` and
     * preloaded with symfony/web-link, its decoding left to the browser.
     */
    public bool $priority = false;

    /** Whether the medium's tiny preview, when the library keeps one, shows behind the image while it loads. */
    public bool $placeholder = true;

    public function __construct(
        private readonly ResponsiveImages $images,
    ) {
    }

    public function medium(): ?Media
    {
        return $this->images->resolve($this->media);
    }

    public function image(Media $media): ImageCandidates
    {
        return $this->images->candidates($media, $this->format, $this->type);
    }

    /**
     * The tiny preview and its position, for the `placeholder` controller; `null` without one or when turned off.
     *
     * @return array{src: string, position: string}|null
     */
    public function preview(Media $media): ?array
    {
        return $this->placeholder ? $this->images->placeholder($media) : null;
    }

    public function loadingMode(): string
    {
        return \in_array($this->loading, ['lazy', 'eager'], true) ? $this->loading : ($this->priority ? 'eager' : 'lazy');
    }

    public function decodingMode(): ?string
    {
        return \in_array($this->decoding, ['async', 'sync', 'auto'], true) ? $this->decoding : ($this->priority ? null : 'async');
    }

    /**
     * Preloads the image, when it has `priority`.
     */
    public function preload(ImageCandidates $image): void
    {
        if ($this->priority) {
            $this->images->preload($image, $this->sizes);
        }
    }

    public function altText(Media $media): string
    {
        return $this->alt ?? $media->alt() ?? '';
    }
}
