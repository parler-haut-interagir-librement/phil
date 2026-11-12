<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Repository;

use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;

interface MediaRepository
{
    public function save(Media|MediaVersion|MediaCollection ...$objects): void;

    public function remove(Media|MediaCollection ...$objects): void;

    public function get(string $id): ?Media;

    /**
     * Several media in one read: a gallery, the image emojis of a page. The media found, by identifier, in the order
     * asked; an identifier asked twice is read once, one that names nothing is left out.
     *
     * @return array<string, Media>
     */
    public function getMany(string ...$ids): array;

    public function collection(string $key): ?MediaCollection;

    /**
     * Every collection, by name.
     *
     * @return list<MediaCollection>
     */
    public function collections(): array;

    /**
     * The media of a collection — or of none —, most recent first.
     *
     * @return list<Media>
     */
    public function inCollection(?MediaCollection $collection, int $limit = 50, int $offset = 0): array;

    /**
     * A medium's previous files, the most recent first.
     *
     * @return list<MediaVersion>
     */
    public function versions(Media $media): array;
}
