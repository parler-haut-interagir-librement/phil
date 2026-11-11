<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\ValueObject;

use Phil\EditLockBundle\Domain\Exception\InvalidLease;

/**
 * Who holds a lease: an identifier the application gives — a user's —, and the name the other writers see. Two
 * holders with the same identifier are the same writer, in whichever tab.
 */
final readonly class Holder
{
    public const int MAX = 255;

    private function __construct(
        public string $id,
        public string $name,
    ) {
    }

    /**
     * @throws InvalidLease when the identifier is empty or longer than 255 bytes, or the name, trimmed, empty or
     *                      longer than 255 characters
     */
    public static function of(string $id, string $name): self
    {
        $name = trim($name);

        if ('' === $id || \strlen($id) > self::MAX) {
            throw InvalidLease::because('A holder is identified by 1 to 255 bytes.');
        }

        if ('' === $name || mb_strlen($name) > self::MAX) {
            throw InvalidLease::because('The name of a holder is 1 to 255 characters.');
        }

        return new self($id, $name);
    }

    public function is(self $other): bool
    {
        return $this->id === $other->id;
    }
}
