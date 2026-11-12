<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Symfony\Component\Uid\Uuid;

/**
 * A collection of media: the photos of an event, the covers of the books.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_media_collection')]
#[ORM\UniqueConstraint(columns: ['collection_key'])]
final class MediaCollection
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'collection_key', length: 64)]
        public readonly string $key,
        #[ORM\Column(name: 'name', length: 150)]
        private string $name,
    ) {
    }

    public static function create(string $key, string $name): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key)) {
            throw InvalidMedia::because(\sprintf('A collection key is 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter; "%s" is not.', $key));
        }

        $collection = new self(Uuid::v7()->toRfc4122(), $key, '');
        $collection->rename($name);

        return $collection;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 150) {
            throw InvalidMedia::because('A collection name is 1 to 150 characters.');
        }

        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
