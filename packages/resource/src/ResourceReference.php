<?php

declare(strict_types=1);

namespace Phil\Resource;

/**
 * A resource of the application named by its kind and its identifier — `page:42`, `event:0192f3c4-…` —: what a
 * trash keeps, a usage index points at, a taxonomy tags or a schedule moves, without knowing the resource's class.
 */
final readonly class ResourceReference
{
    private function __construct(
        public string $key,
        public string $id,
    ) {
    }

    public static function of(string $key, string $id): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $key)) {
            throw InvalidResource::because(\sprintf('A resource kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $key));
        }

        if ('' === $id || \strlen($id) > 255) {
            throw InvalidResource::because('A resource identifier is 1 to 255 bytes.');
        }

        return new self($key, $id);
    }

    /**
     * The reference `toString()` wrote: the kind, a colon, the identifier — which may hold colons of its own.
     */
    public static function fromString(string $reference): self
    {
        $parts = explode(':', $reference, 2);

        if (2 !== \count($parts)) {
            throw InvalidResource::because(\sprintf('"%s" is not a resource reference of the form "kind:identifier".', $reference));
        }

        return self::of($parts[0], $parts[1]);
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key && $this->id === $other->id;
    }

    public function toString(): string
    {
        return $this->key.':'.$this->id;
    }
}
