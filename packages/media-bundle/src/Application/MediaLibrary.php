<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use DateTimeImmutable;
use Phil\MediaBundle\Application\Contract\SvgPurifier;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Exception\MediaInUse;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Psr\Clock\ClockInterface;

/**
 * The media library: files uploaded and replaced, their versions kept, deleted with everything rendered from them;
 * their images rendered to the configured formats, and their narrower widths, on first request, then kept. The
 * format `original` is the file itself, converted whole to another type.
 *
 * With a placeholder format, each version of a raster image gets its tiny preview, a WebP `data:` URL kept on the
 * medium, computed once when the file arrives. With an SVG purifier, an SVG image is purified and optimised before it
 * is kept, and purified again the first time it is served, so that one kept before the purifier was turned on is
 * served as safely as the others.
 */
final readonly class MediaLibrary
{
    public const array TYPES = ['webp' => 'image/webp', 'avif' => 'image/avif', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    /** The media types of the images the processors read: the only ones rendered to formats and previews. */
    public const array RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    public const string SVG = 'image/svg+xml';

    /** The longest placeholder kept: a preview that comes out larger is not worth inlining in every page. */
    public const int PLACEHOLDER_MAX = 4096;

    /**
     * @param array<string, ImageFormat> $formats     by name
     * @param ImageFormat|null           $original    how the file itself is converted; quality 80, no narrower width by default
     * @param MediaReferences            $references  the records that show media, which a deletion respects; none by default
     * @param ImageFormat|null           $placeholder the format of the tiny previews, rendered to WebP; none by default
     * @param SvgPurifier|null           $svg         what purifies SVG images before they are kept; kept as uploaded by default
     */
    public function __construct(
        private MediaRepository $media,
        private MediaStorage $storage,
        private ImageProcessor $images,
        private ClockInterface $clock,
        private array $formats = [],
        private ?ImageFormat $original = null,
        private MediaReferences $references = new NoMediaReferences(),
        private ?ImageFormat $placeholder = null,
        private ?SvgPurifier $svg = null,
    ) {
    }

    public function upload(string $path, string $filename, ?MediaCollection $collection = null): Media
    {
        [$file, $contents] = $this->read($path, $filename);
        $media = Media::upload($file, $collection, $this->now());
        $this->storage->write($media->storageKey(), $contents);
        $this->media->save($media);
        $this->placeholder($media);

        return $media;
    }

    /**
     * Gives the medium a new file. The renderings of the previous version are deleted: its URLs answer `404`.
     */
    public function replace(Media $media, string $path, string $filename): void
    {
        $previous = $media->version();
        [$file, $contents] = $this->read($path, $filename);
        $version = $media->replace($file, $this->now());
        $this->storage->write($media->storageKey(), $contents);
        $this->media->save($media, $version);
        $this->storage->deletePrefix(\sprintf('formats/%s/%d/', $media->id, $previous));
        $this->placeholder($media);
    }

    /**
     * The tiny preview of the medium's current version, as a `data:` URL: the one kept, or, with a placeholder format,
     * rendered once and kept with the medium. `null` without a placeholder format, for a file that is not a raster
     * image of known size, or when the image cannot be rendered.
     */
    public function placeholder(Media $media): ?string
    {
        $file = $media->file();

        if (null !== $media->placeholder() || !$this->placeholder instanceof ImageFormat || null === $file->width || !\in_array($file->mimeType, self::RASTER_TYPES, true)) {
            return $media->placeholder();
        }

        try {
            $placeholder = 'data:image/webp;base64,'.sodium_bin2base64($this->images->render($this->original($media), $this->placeholder, $media->focalPoint(), 'webp'), \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (InvalidMedia) {
            return null;
        }

        if (\strlen($placeholder) > self::PLACEHOLDER_MAX) {
            return null;
        }

        $media->preview($placeholder);
        $this->media->save($media);

        return $placeholder;
    }

    /**
     * Whether the library computes tiny previews: a placeholder format is configured.
     */
    public function hasPlaceholders(): bool
    {
        return $this->placeholder instanceof ImageFormat;
    }

    /**
     * The medium's SVG image purified, to be served as `image/svg+xml`: purified the first time it is asked for, then
     * kept beside the formats of its version and read back. `null` without an SVG purifier or for a file that is not an
     * SVG image, which is then served as it was kept.
     */
    public function purifiedSvg(Media $media): ?string
    {
        if (!$this->svg instanceof SvgPurifier || self::SVG !== $media->file()->mimeType) {
            return null;
        }

        $key = \sprintf('formats/%s/%d/purified.svg', $media->id, $media->version());
        $purified = $this->storage->read($key);

        if (null === $purified) {
            $purified = $this->svg->purify($this->original($media));
            $this->storage->write($key, $purified);
        }

        return $purified;
    }

    /**
     * Moves the medium's focal point and saves it. When it moves, the renderings around the previous one are deleted;
     * the formats are rendered anew on their next request.
     */
    public function focus(Media $media, float $x, float $y): void
    {
        $before = $media->focalPoint();
        $media->focus($x, $y);
        $this->media->save($media);

        if ($this->renderedKey($before) !== $this->renderedKey($media->focalPoint())) {
            $this->storage->deletePrefix(\sprintf('formats/%s/%d/', $media->id, $media->version()));
        }
    }

    /**
     * Deletes the medium, its versions and everything rendered from it. A record that still shows it decides first: a
     * column that restricts refuses the deletion with `MediaInUse`, one set to `set_null` is emptied, one that keeps
     * is left alone.
     */
    public function delete(Media $media): void
    {
        $id = MediaId::fromString($media->id);
        $references = $this->references->of($id);
        $places = [];

        foreach ($references as $reference) {
            if (DeleteBehaviour::Restrict === $reference->behaviour) {
                $places[] = $reference->place();
            }
        }

        if ([] !== $places) {
            throw MediaInUse::by($media->id, $places);
        }

        foreach ($references as $reference) {
            if (DeleteBehaviour::SetNull === $reference->behaviour) {
                $this->references->detach($id, $reference);
            }
        }

        $this->storage->deletePrefix('originals/'.$media->id);
        $this->storage->deletePrefix('formats/'.$media->id);

        $this->media->remove($media);
    }

    /**
     * The records that show the medium, column by column.
     *
     * @return list<MediaReference>
     */
    public function usages(Media $media): array
    {
        return $this->references->of(MediaId::fromString($media->id));
    }

    public function original(Media $media): string
    {
        return $this->storage->read($media->storageKey()) ?? throw InvalidMedia::because(\sprintf('The file of %s is missing.', $media->id));
    }

    /**
     * The medium's image in a format and a file type, at one of the format's narrower widths or not, rendered the
     * first time, then read back.
     */
    public function rendered(Media $media, string $format, string $type, ?int $width = null): string
    {
        $imageFormat = $this->format($format);
        $imageFormat = null === $width ? $imageFormat : $imageFormat->atWidth($width);

        if (!isset(self::TYPES[$type])) {
            throw InvalidMedia::because(\sprintf('An image is rendered as %s; not as "%s".', implode(', ', array_keys(self::TYPES)), $type));
        }

        if (!$media->file()->isImage()) {
            throw InvalidMedia::because(\sprintf('%s is not an image.', $media->id));
        }

        [$x, $y] = $media->focalPoint();
        $key = \sprintf('formats/%s/%d/%s-%s%s.%s', $media->id, $media->version(), $format, $this->renderedKey([$x, $y]), null === $width ? '' : '@'.$width, $type);
        $rendered = $this->storage->read($key);

        if (null === $rendered) {
            $rendered = $this->images->render($this->original($media), $imageFormat, [$x, $y], $type);
            $this->storage->write($key, $rendered);
        }

        return $rendered;
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return array_keys($this->formats);
    }

    /**
     * A declared format, or `original`.
     */
    public function format(string $name): ImageFormat
    {
        if ('original' === $name) {
            return $this->original ?? ImageFormat::original();
        }

        return $this->formats[$name] ?? throw InvalidMedia::because(\sprintf('There is no format "%s".', $name));
    }

    /**
     * The focal point in hundredths, as rendered files are named: `50-50`.
     *
     * @param array{float, float} $focalPoint
     */
    private function renderedKey(array $focalPoint): string
    {
        return \sprintf('%d-%d', (int) round($focalPoint[0] * 100), (int) round($focalPoint[1] * 100));
    }

    /**
     * What is known of the file and its contents, an SVG image purified first.
     *
     * @return array{FileInfo, string}
     */
    private function read(string $path, string $filename): array
    {
        $size = @filesize($path);

        if (false === $size) {
            throw InvalidMedia::because(\sprintf('The file "%s" cannot be read.', $filename));
        }

        $mimeType = mime_content_type($path);
        $mimeType = \is_string($mimeType) ? $mimeType : 'application/octet-stream';

        $contents = (string) file_get_contents($path);

        if ($this->svg instanceof SvgPurifier && self::SVG === $mimeType) {
            $contents = $this->svg->purify($contents);

            return [FileInfo::of($filename, $mimeType, \strlen($contents)), $contents];
        }

        $dimensions = @getimagesize($path);

        return [FileInfo::of($filename, $mimeType, $size, false === $dimensions ? null : $dimensions[0], false === $dimensions ? null : $dimensions[1]), $contents];
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
