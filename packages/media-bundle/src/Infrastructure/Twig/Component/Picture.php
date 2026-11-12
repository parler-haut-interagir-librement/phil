<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Twig\Component;

use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Twig\ImageCandidates;
use Phil\MediaBundle\Infrastructure\Twig\ResponsiveImages;

/**
 * `<twig:PhilMedia:Picture :media="post.cover" format="card" sizes="100vw"/>`: a `<picture>` whose `<source>`s offer
 * the format in AVIF then WebP — or the `types` given —, each with its `srcset`, and whose `<img>` falls back to PNG
 * for an original that may be transparent, JPEG otherwise, with the width and height, `loading="lazy"` and
 * `decoding="async"` — or, with `priority`, `loading="eager"` and `fetchpriority="high"`, and a preload link of the
 * first source. The other attributes go on the `<img>`. A medium that cannot be rendered is a plain `<img>` of its
 * file.
 */
final class Picture
{
    public Media|MediaId|string|null $media = null;

    public string $format = 'original';

    /**
     * @var list<string>
     */
    public array $types = ['avif', 'webp'];

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

    /**
     * @return list<ImageCandidates>
     */
    public function sources(Media $media): array
    {
        if (!$this->images->renderable($media)) {
            return [];
        }

        return array_map(fn (string $type): ImageCandidates => $this->images->candidates($media, $this->format, $type), $this->types);
    }

    public function fallback(Media $media): ImageCandidates
    {
        return $this->images->candidates($media, $this->format, $this->images->fallbackType($media));
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
     * Preloads the first source, or the image itself without sources, when it has `priority`.
     */
    public function preload(Media $media): void
    {
        if (!$this->priority) {
            return;
        }

        $sources = $this->sources($media);

        if ([] === $sources) {
            $this->images->preload($this->fallback($media), $this->sizes);

            return;
        }

        $this->images->preload($sources[0], $this->sizes, true);
    }

    public function altText(Media $media): string
    {
        return $this->alt ?? $media->alt() ?? '';
    }
}
