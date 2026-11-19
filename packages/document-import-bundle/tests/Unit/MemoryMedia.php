<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use LogicException;
use Override;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\Repository\MediaRepository;

/**
 * The media library's records, files and image processor in memory: what was saved, in order, and the bytes written.
 */
final class MemoryMedia implements MediaRepository, MediaStorage, ImageProcessor
{
    /** @var list<Media|MediaVersion|MediaCollection> */
    public array $saved = [];

    /** @var array<string, string> */
    public array $files = [];

    /** @var array<string, MediaCollection> */
    public array $collections = [];

    public bool $failing = false;

    #[Override]
    public function save(Media|MediaVersion|MediaCollection ...$objects): void
    {
        foreach ($objects as $object) {
            $this->saved[] = $object;

            if ($object instanceof MediaCollection) {
                $this->collections[$object->key] = $object;
            }
        }
    }

    #[Override]
    public function remove(Media|MediaCollection ...$objects): void
    {
        throw new LogicException('Nothing is removed.');
    }

    #[Override]
    public function get(string $id): ?Media
    {
        return null;
    }

    #[Override]
    public function getMany(string ...$ids): array
    {
        return [];
    }

    #[Override]
    public function collection(string $key): ?MediaCollection
    {
        return $this->collections[$key] ?? null;
    }

    #[Override]
    public function collections(): array
    {
        return array_values($this->collections);
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
        if ($this->failing) {
            throw new LogicException('The disk is full.');
        }

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
        throw new LogicException('Nothing is rendered.');
    }
}
