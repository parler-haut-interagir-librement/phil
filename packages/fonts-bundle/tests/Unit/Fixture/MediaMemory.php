<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Psr\Clock\ClockInterface;

/**
 * The repository, storage, image processor and clock of a media library of phil/media-bundle, held in memory, for the tests of the integration.
 */
final class MediaMemory implements MediaRepository, MediaStorage, ImageProcessor, ClockInterface
{
    /** @var array<string, Media> */
    private array $media = [];

    /** @var array<string, string> */
    private array $files = [];

    public function library(): MediaLibrary
    {
        return new MediaLibrary($this, $this, $this, $this);
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
        return [];
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
        return $this->files[$key] ?? null;
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
    }

    #[Override]
    public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
    {
        return $image;
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-07 12:00:00');
    }
}
