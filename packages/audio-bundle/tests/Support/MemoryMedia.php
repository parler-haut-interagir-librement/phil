<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Support;

use LogicException;
use Override;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Symfony\Component\Clock\MockClock;
use Throwable;

/**
 * phil/media-bundle in memory: its repository, its storage, and a library on them that renders no image.
 */
final class MemoryMedia implements MediaRepository, MediaStorage, ImageProcessor
{
    /** @var array<string, Media> */
    public array $media = [];

    /** @var array<string, string> */
    public array $files = [];

    /**
     * Thrown by every read, to stand for a storage that fails.
     */
    public ?Throwable $readFailure = null;

    public function library(): MediaLibrary
    {
        return new MediaLibrary($this, $this, $this, new MockClock('2026-11-14 10:00:00'));
    }

    #[Override]
    public function save(Media|MediaVersion|MediaCollection ...$objects): void
    {
        foreach ($objects as $object) {
            if ($object instanceof Media) {
                $this->media[$object->id] = $object;
            }
        }
    }

    #[Override]
    public function remove(Media|MediaCollection ...$objects): void
    {
        foreach ($objects as $object) {
            if ($object instanceof Media) {
                unset($this->media[$object->id]);
            }
        }
    }

    #[Override]
    public function get(string $id): ?Media
    {
        return $this->media[$id] ?? null;
    }

    #[Override]
    public function getMany(string ...$ids): array
    {
        return array_intersect_key($this->media, array_flip($ids));
    }

    #[Override]
    public function collection(string $key): ?MediaCollection
    {
        return null;
    }

    #[Override]
    public function collections(): array
    {
        return [];
    }

    #[Override]
    public function inCollection(?MediaCollection $collection, int $limit = 50, int $offset = 0): array
    {
        return array_values($this->media);
    }

    #[Override]
    public function versions(Media $media): array
    {
        return [];
    }

    #[Override]
    public function write(string $key, string $contents): void
    {
        $this->files[$key] = $contents;
    }

    #[Override]
    public function read(string $key): ?string
    {
        if ($this->readFailure instanceof Throwable) {
            throw $this->readFailure;
        }

        return $this->files[$key] ?? null;
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $this->files = array_filter($this->files, static fn (string $key): bool => !str_starts_with($key, $prefix), \ARRAY_FILTER_USE_KEY);
    }

    #[Override]
    public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
    {
        throw new LogicException('No image is rendered.');
    }
}
