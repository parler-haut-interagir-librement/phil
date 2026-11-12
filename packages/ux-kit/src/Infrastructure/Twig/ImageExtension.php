<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\Twig;

use InvalidArgumentException;
use LogicException;
use Phil\UxKit\Application\ImageOrigin;
use Phil\UxKit\Application\ImageSources;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * What the `image` recipe asks of PHP: where an image comes from, read by `phil/url`, and the props of a picture from
 * the assets of AssetMapper.
 */
final readonly class ImageExtension
{
    public function __construct(
        private ImageOrigin $origin,
        private ?RequestStack $requests = null,
        private ?AssetMapperInterface $assets = null,
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
}
