<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Infrastructure\Media;

use Closure;
use Override;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaStore;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media as Medium;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Repository\MediaRepository;

/**
 * The images of an imported document kept in the media library of `phil/media-bundle`: each one uploaded as a new
 * medium, in the configured collection — created the first time —, with the text alternative its author gave it,
 * and shown at the address the application's closure gives the medium.
 */
final readonly class MediaLibraryStore implements MediaStore
{
    /** The longest alternative text a medium keeps. */
    private const int ALT_LENGTH = 500;

    /**
     * @param Closure(Medium): string $address    the address an image block shows for a medium
     * @param string|null             $collection the key of the collection the media go to; none by default
     */
    public function __construct(
        private MediaLibrary $library,
        private MediaRepository $repository,
        private Closure $address,
        private ?string $collection = null,
    ) {
    }

    #[Override]
    public function store(Media $media): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-import-');

        if (false === $path || false === file_put_contents($path, $media->contents)) {
            throw InvalidMedia::because(\sprintf('The image "%s" cannot be written to a temporary file.', $media->filename()));
        }

        try {
            $medium = $this->library->upload($path, $media->filename(), $this->collection());
        } finally {
            unlink($path);
        }

        $alt = trim($media->description);

        if ('' !== $alt) {
            $medium->describe(null, alt: mb_substr($alt, 0, self::ALT_LENGTH));
            $this->repository->save($medium);
        }

        return ($this->address)($medium);
    }

    private function collection(): ?MediaCollection
    {
        if (null === $this->collection) {
            return null;
        }

        $collection = $this->repository->collection($this->collection);

        if (!$collection instanceof MediaCollection) {
            $collection = MediaCollection::create($this->collection, $this->collection);
            $this->repository->save($collection);
        }

        return $collection;
    }
}
