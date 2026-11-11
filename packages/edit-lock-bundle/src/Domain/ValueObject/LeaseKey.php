<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\ValueObject;

use Phil\EditLockBundle\Domain\Exception\InvalidLease;

/**
 * What a lease is on: a resource, named by the application — `article:42`, `wiki/home` —, and optionally one part of
 * it, the identifier of a block of its document. A lease on the whole resource excludes the leases of other holders on
 * its parts, and a lease on a part excludes another holder's lease on the same part or on the whole resource.
 */
final readonly class LeaseKey
{
    /** The identifier of a part: that of a block in a UJO document. */
    public const string PART = '/^[A-Za-z0-9_-]{1,64}$/D';

    public const int MAX_RESOURCE = 255;

    private function __construct(
        public string $resource,
        public ?string $part,
    ) {
    }

    /**
     * @throws InvalidLease when the resource is empty, longer than 255 bytes or holds a control character, or the part
     *                      is not 1 to 64 letters, digits, `_` or `-`
     */
    public static function of(string $resource, ?string $part = null): self
    {
        if ('' === $resource || \strlen($resource) > self::MAX_RESOURCE || 1 === preg_match('/[\x00-\x1F\x7F]/', $resource)) {
            throw InvalidLease::because('A resource is named by 1 to 255 bytes without control characters.');
        }

        if (null !== $part && 1 !== preg_match(self::PART, $part)) {
            throw InvalidLease::because('A part is named by 1 to 64 letters, digits, "_" or "-".');
        }

        return new self($resource, $part);
    }

    public function isWhole(): bool
    {
        return null === $this->part;
    }

    public function equals(self $other): bool
    {
        return $this->resource === $other->resource && $this->part === $other->part;
    }

    /**
     * Whether a lease on this key and one on the other cannot have two holders: the same resource, and the same part
     * or the whole of it on either side.
     */
    public function overlaps(self $other): bool
    {
        return $this->resource === $other->resource && ($this->isWhole() || $other->isWhole() || $this->part === $other->part);
    }

    /**
     * Whether a lease on this key lets its holder write the other: the same key, or the whole resource.
     */
    public function covers(self $other): bool
    {
        return $this->resource === $other->resource && ($this->isWhole() || $this->part === $other->part);
    }

    public function toString(): string
    {
        return null === $this->part ? $this->resource : $this->resource.'#'.$this->part;
    }
}
