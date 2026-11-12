<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\ValueObject;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Stringable;

/**
 * The identifier of a medium, as another entity keeps it in a column of type `phil_media`: a reference that does not
 * load the medium until the page needs it.
 */
final readonly class MediaId implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value)) {
            throw InvalidMedia::because(\sprintf('A medium is identified by a lower-case UUID; "%s" is not one.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
