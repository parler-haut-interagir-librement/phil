<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The record an audited event is about: a type the application names, such as `order` or `page`, and the stable
 * identifier of one record of that type. Never a name, an address or any other personal value: the pair is indexed and
 * hashed into the row, so it cannot be erased.
 */
final readonly class Subject
{
    private function __construct(
        public string $type,
        public string $id,
    ) {
    }

    /**
     * @throws InvalidValue when the type is not 1 to 64 lower-case letters, digits, ".", "_" or "-" starting with a
     *                      letter, or the identifier not 1 to 128 letters, digits, ".", "_", ":" or "-"
     */
    public static function of(string $type, string $id): self
    {
        self::assertType($type);
        Facts::assertIdentifier('A subject identifier', $id);

        return new self($type, $id);
    }

    /**
     * The subject as storage holds it, unchecked: {@see Facts::isWellFormed()} checks it.
     */
    public static function restore(string $type, string $id): self
    {
        return new self($type, $id);
    }

    /**
     * @throws InvalidValue when the type is not 1 to 64 lower-case letters, digits, ".", "_" or "-" starting with a
     *                      letter
     */
    public static function assertType(string $type): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $type)) {
            throw InvalidValue::because(\sprintf('A subject type is 1 to 64 lower-case letters, digits, ".", "_" or "-", starting with a letter; "%s" is not.', $type));
        }
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
