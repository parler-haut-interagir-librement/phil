<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Domain\Exception\InvalidTrashItem;
use Symfony\Component\Uid\Uuid;

/**
 * A resource in the trash: what it was, what its handler needs to bring it back, the parent it was under, who put
 * it there and when, and when it goes for good. Times are kept in UTC.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_trash_item')]
#[ORM\Index(columns: ['resource_key', 'resource_id'])]
#[ORM\Index(columns: ['purge_after'])]
final class TrashItem
{
    private const string UTC = 'UTC';

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'resource_key', length: 64)]
        private string $resourceKey,
        #[ORM\Column(name: 'resource_id', length: 255)]
        private string $resourceId,
        #[ORM\Column(name: 'title', length: 255)]
        private string $title,
        #[ORM\Column(name: 'payload', type: 'json')]
        private array $payload,
        #[ORM\Column(name: 'parent', length: 320, nullable: true)]
        private ?string $parent,
        #[ORM\Column(name: 'trashed_at')]
        private DateTimeImmutable $trashedAt,
        #[ORM\Column(name: 'trashed_by', length: 255, nullable: true)]
        private ?string $trashedBy,
        #[ORM\Column(name: 'purge_after')]
        private DateTimeImmutable $purgeAfter,
    ) {
    }

    /**
     * @param array<string, mixed> $payload what the handler needs to restore the resource, as JSON
     */
    public static function of(ResourceReference $resource, string $title, array $payload, ?ResourceReference $parent, DateTimeImmutable $trashedAt, ?string $trashedBy, DateTimeImmutable $purgeAfter): self
    {
        $title = trim($title);

        if ('' === $title || mb_strlen($title) > 255) {
            throw InvalidTrashItem::because('The title of a trashed resource is 1 to 255 characters.');
        }

        if (null !== $trashedBy && ('' === $trashedBy || \strlen($trashedBy) > 255)) {
            throw InvalidTrashItem::because('Who trashed a resource is 1 to 255 bytes, or nobody.');
        }

        if ($purgeAfter < $trashedAt) {
            throw InvalidTrashItem::because('A trashed resource is purged after it was trashed.');
        }

        return new self(Uuid::v7()->toRfc4122(), $resource->key, $resource->id, $title, $payload, $parent?->toString(), self::utc($trashedAt), $trashedBy, self::utc($purgeAfter));
    }

    public function resource(): ResourceReference
    {
        return ResourceReference::of($this->resourceKey, $this->resourceId);
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function parent(): ?ResourceReference
    {
        if (null === $this->parent) {
            return null;
        }

        return ResourceReference::fromString($this->parent);
    }

    public function trashedAt(): DateTimeImmutable
    {
        return $this->read($this->trashedAt);
    }

    public function trashedBy(): ?string
    {
        return $this->trashedBy;
    }

    public function purgeAfter(): DateTimeImmutable
    {
        return $this->read($this->purgeAfter);
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->purgeAfter() <= $now;
    }

    private static function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone(self::UTC));
    }

    /**
     * Doctrine reads a `datetime_immutable` in the server's time zone: the stored wall clock is UTC.
     */
    private function read(DateTimeImmutable $stored): DateTimeImmutable
    {
        return new DateTimeImmutable($stored->format('Y-m-d H:i:s.u'), new DateTimeZone(self::UTC));
    }
}
