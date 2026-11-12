<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\Twig;

use InvalidArgumentException;
use LogicException;
use Phil\UxKit\Application\ImageOrigin;
use Phil\UxKit\Application\ImageSources;
use Phil\UxKit\Application\ImageTextCheck;
use Phil\UxKit\Application\PictureProps;
use Phil\UxKit\Infrastructure\LiipImagine\ImageFilters;
use Phil\UxKit\Infrastructure\StructuredData\ImageJsonLd;
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * What the `image` recipe asks of PHP: where an image comes from, read by `phil/url`, the props of a picture from the
 * assets of AssetMapper, from the filter sets of LiipImagine or from an object a provider knows, the `ImageObject` of an
 * image in JSON-LD, and, while the site is developed, a warning for a text alternative or a size left wrong.
 */
final readonly class ImageExtension
{
    public function __construct(
        private ImageOrigin $origin,
        private ?RequestStack $requests = null,
        private ?AssetMapperInterface $assets = null,
        private ?ImageTextCheck $check = null,
        private ?LoggerInterface $logger = null,
        private ?ImageFilters $filters = null,
        private PictureProps $pictures = new PictureProps(),
        private ?ImageJsonLd $jsonLd = null,
    ) {
    }

    /**
     * `local`, `external` or `refused`: the origin of an address, or of every candidate of a `srcset` when `set` is
     * true, the page's own origin being that of the main request.
     */
    #[AsTwigFunction(name: 'image_origin')]
    public function origin(?string $url, bool $set = false): string
    {
        if (null === $url) {
            return ImageOrigin::REFUSED;
        }

        $origin = $this->requests?->getMainRequest()?->getSchemeAndHttpHost();

        return $set ? $this->origin->ofSet($url, $origin) : $this->origin->of($url, $origin);
    }

    /**
     * The `src`, `srcset`, `sources`, `width` and `height` of a `Picture` from the files of one image among the assets,
     * `images/logo.avif`, `images/logo@2x.avif`…, to spread into it: `<twig:Picture {{ ...image_sources('images/logo') }} alt="…" />`.
     *
     * @param array{densities?: list<int>, widths?: list<int>, formats?: list<string>, fallbacks?: list<string>} $options
     *
     * @return array{src: string, srcset?: string, sources: list<array{type: string, srcset: string}>, width?: int, height?: int}
     *
     * @throws LogicException           when AssetMapper is not installed
     * @throws InvalidArgumentException when the image has no fallback among the assets
     */
    #[AsTwigFunction(name: 'image_sources')]
    public function sources(string $path, array $options = []): array
    {
        if (!$this->assets instanceof AssetMapperInterface) {
            throw new LogicException('image_sources() reads the assets of AssetMapper: run "composer require symfony/asset-mapper".');
        }

        return new ImageSources($this->assets)->of($path, $options);
    }

    /**
     * Logs a warning for each problem the check finds in an image, its text alternative or its size, when the check is
     * on, in development; does nothing otherwise, and never changes the page.
     */
    #[AsTwigFunction(name: 'image_check')]
    public function check(?string $src, ?string $alt, mixed $width = null, mixed $height = null): void
    {
        if (!$this->check instanceof ImageTextCheck || !$this->logger instanceof LoggerInterface) {
            return;
        }

        foreach ($this->check->problems($src ?? '', $alt ?? '', $width, $height) as $problem => $message) {
            $this->logger->warning($message, ['src' => $src, 'alt' => $alt, 'problem' => $problem]);
        }
    }

    /**
     * The `src`, `srcset`, `sources`, `width` and `height` of a `Picture` from the filter sets of LiipImagine applied to
     * one image, each set a candidate of its width: `<twig:Picture {{ ...image_filters('uploads/hall.jpg', ['hall_480', 'hall_960']) }} sizes="100vw" alt="…" />`.
     *
     * @param list<string>|array<string, int> $filters the names of the filter sets, or each name with its width
     *
     * @return array{src: string, srcset?: string, sources: list<array{type: string, srcset: string}>, width?: int, height?: int}
     *
     * @throws LogicException           when LiipImagine is not installed
     * @throws InvalidArgumentException when a filter set is unknown or its width can be read nowhere
     */
    #[AsTwigFunction(name: 'image_filters')]
    public function filters(string $path, array $filters): array
    {
        if (!$this->filters instanceof ImageFilters) {
            throw new LogicException('image_filters() reads the filter sets of LiipImagine: run "composer require liip/imagine-bundle".');
        }

        return $this->filters->of($path, $filters);
    }

    /**
     * The props of a `Picture` for an object of the application, a medium of a library or an image of a catalogue, from
     * the first `PicturePropsProvider` that supports it: `<twig:Picture {{ ...picture_props(medium) }} />`.
     *
     * @param array<string, mixed> $options what the template asks of the provider
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when no provider supports the object
     */
    #[AsTwigFunction(name: 'picture_props')]
    public function pictureProps(object $subject, array $options = []): array
    {
        return $this->pictures->of($subject, $options);
    }

    /**
     * The `ImageObject` of an image, from the props `src`, `width`, `height`, `caption`, `credit`, `licenseHref` and
     * `creation`: added to the graph of the page when an application gathers one, and nothing written; its own
     * `<script type="application/ld+json">` otherwise. Nothing for an inline image or an address `phil/url` refuses.
     *
     * @param array<array-key, mixed> $image
     *
     * @throws LogicException when phil/structured-data is not installed
     */
    #[AsTwigFunction(name: 'image_json_ld', isSafe: ['html'])]
    public function jsonLd(array $image): string
    {
        if (!$this->jsonLd instanceof ImageJsonLd) {
            throw new LogicException('image_json_ld() writes through phil/structured-data: run "composer require phil/structured-data".');
        }

        return $this->jsonLd->script($image);
    }
}
