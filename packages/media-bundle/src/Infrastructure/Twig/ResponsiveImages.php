<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Twig;

use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\Transform\Geometry;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Psr\Link\EvolvableLinkProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

/**
 * What the `Img` and `Picture` components print for a medium in a format: each file type's URL, the `srcset` over the
 * format's narrower widths, and the size the format comes out at, computed from the original's size and focal point
 * without rendering anything. A medium that cannot be rendered — a PDF, an SVG, an image whose size is unknown — is
 * its file itself, without a size or a `srcset`.
 *
 * With symfony/web-link, the main image of a page is preloaded: a `Link: <…>; rel=preload; as=image` header, which the
 * web server may send early as a `103 Early Hints`.
 */
final readonly class ResponsiveImages
{
    /**
     * The types whose renderings keep transparency: an original in one of them falls back to PNG rather than JPEG.
     */
    private const array TRANSPARENT = ['image/png', 'image/gif', 'image/webp', 'image/avif'];

    public function __construct(
        private MediaLibrary $library,
        private MediaExtension $urls,
        private MediaRepository $media,
        private ?RequestStack $requests = null,
    ) {
    }

    /**
     * The medium, from itself, its identifier or the identifier's text; `null` when there is none, or no longer.
     */
    public function resolve(Media|MediaId|string|null $media): ?Media
    {
        if ($media instanceof Media || null === $media) {
            return $media;
        }

        try {
            return $this->media->get($media instanceof MediaId ? $media->value : MediaId::fromString($media)->value);
        } catch (InvalidMedia) {
            return null;
        }
    }

    public function candidates(Media $media, string $format, string $type): ImageCandidates
    {
        $original = $this->geometry($media);

        if (!$original instanceof Geometry) {
            return new ImageCandidates($this->urls->url($media), null, null, null, $media->file()->mimeType);
        }

        $mimeType = MediaLibrary::TYPES[$type] ?? throw InvalidMedia::because(\sprintf('An image is rendered as %s; not as "%s".', implode(', ', array_keys(MediaLibrary::TYPES)), $type));
        $imageFormat = $this->library->format($format);
        $size = $imageFormat->apply($original);
        $srcset = [];

        foreach ($imageFormat->widths as $width) {
            $narrow = $imageFormat->atWidth($width)->apply($original);

            if ($narrow->width < $size->width) {
                $srcset[] = $this->urls->url($media, $format, $type, $width).' '.$narrow->width.'w';
            }
        }

        $src = $this->urls->url($media, $format, $type);

        return new ImageCandidates($src, [] === $srcset ? null : implode(', ', [...$srcset, $src.' '.$size->width.'w']), $size->width, $size->height, $mimeType);
    }

    /**
     * The type every browser shows: PNG for an original that may be transparent, JPEG otherwise.
     */
    public function fallbackType(Media $media): string
    {
        return \in_array($media->file()->mimeType, self::TRANSPARENT, true) ? 'png' : 'jpeg';
    }

    /**
     * The medium's tiny preview and where it is centred — the focal point, `25% 100%` —, which the `placeholder`
     * Stimulus controller paints behind the image while it loads, through the CSSOM rather than a `style` attribute
     * that a strict Content Security Policy drops; `null` when the medium has none. The preview is the one kept with
     * the medium: a page never renders one.
     *
     * @return array{src: string, position: string}|null
     */
    public function placeholder(Media $media): ?array
    {
        $placeholder = $media->placeholder();

        if (null === $placeholder) {
            return null;
        }

        [$x, $y] = $media->focalPoint();

        return ['src' => $placeholder, 'position' => \sprintf('%d%% %d%%', (int) round($x * 100), (int) round($y * 100))];
    }

    /**
     * Asks the browser to fetch the image first, before it reads the page: a preload link on the response of the
     * current request, with the candidates, their sizes and the media type of a `<source>`. Without symfony/web-link
     * or outside a request, nothing.
     */
    public function preload(ImageCandidates $image, ?string $sizes = null, bool $typed = false): void
    {
        $request = $this->requests?->getCurrentRequest();

        if (!$request instanceof Request || !class_exists(GenericLinkProvider::class)) {
            return;
        }

        $link = new Link(Link::REL_PRELOAD, $image->src)->withAttribute('as', 'image')->withAttribute('fetchpriority', 'high');

        if (null !== $image->srcset) {
            $link = $link->withAttribute('imagesrcset', $image->srcset);
            $link = null === $sizes ? $link : $link->withAttribute('imagesizes', $sizes);
        }

        $link = $typed ? $link->withAttribute('type', $image->mimeType) : $link;
        $links = $request->attributes->get('_links', new GenericLinkProvider());

        if ($links instanceof EvolvableLinkProviderInterface) {
            $request->attributes->set('_links', $links->withLink($link));
        }
    }

    public function renderable(Media $media): bool
    {
        return $this->geometry($media) instanceof Geometry;
    }

    /**
     * The original's size and focal point, when it is an image GD renders and its size is known.
     */
    private function geometry(Media $media): ?Geometry
    {
        $file = $media->file();

        if (null === $file->width || null === $file->height || !\in_array($file->mimeType, ['image/jpeg', ...self::TRANSPARENT], true)) {
            return null;
        }

        [$x, $y] = $media->focalPoint();

        return Geometry::of($file->width, $file->height, $x, $y);
    }
}
