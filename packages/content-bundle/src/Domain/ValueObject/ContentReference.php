<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\ValueObject;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Stringable;

/**
 * Names one content root from outside the storage: its resource key, the kind of content (`news`,
 * `static_page`), and its identifier as a string (`0192f3c4-…`, `42`).
 *
 * The same two parts a `Link` to a content root carries, with the same shapes: a route points to its root
 * through one, a console command and a migration address a root by one. **The string form of the identifier
 * is the application's**: the storage turns it back into the root's own identifier when it loads the root.
 */
final readonly class ContentReference implements Stringable
{
    private const string RESOURCE_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/D';

    private const string RESOURCE_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

    private function __construct(
        public string $resourceKey,
        public string $resourceId,
    ) {
    }

    /**
     * @throws InvalidContent
     */
    public static function of(string $resourceKey, string $resourceId): self
    {
        if (!self::isResourceKey($resourceKey)) {
            throw InvalidContent::because(\sprintf('"%s" is not a resource key: a lowercase letter, then at most 63 lowercase letters, digits or underscores.', $resourceKey));
        }

        if (1 !== preg_match(self::RESOURCE_ID_PATTERN, $resourceId)) {
            throw InvalidContent::because(\sprintf('"%s" is not a resource identifier: 1 to 64 letters, digits, hyphens or underscores.', $resourceId));
        }

        return new self($resourceKey, $resourceId);
    }

    /**
     * Reads back the form `toString()` writes: `news:0192f3c4-…`.
     *
     * @throws InvalidContent
     */
    public static function fromString(string $value): self
    {
        $parts = explode(':', $value);

        if (2 !== \count($parts)) {
            throw InvalidContent::because(\sprintf('"%s" is not a content reference of the form "resource_key:identifier".', $value));
        }

        return self::of($parts[0], $parts[1]);
    }

    public static function isResourceKey(string $resourceKey): bool
    {
        return 1 === preg_match(self::RESOURCE_KEY_PATTERN, $resourceKey);
    }

    public function equals(self $other): bool
    {
        return $this->resourceKey === $other->resourceKey && $this->resourceId === $other->resourceId;
    }

    public function toString(): string
    {
        return $this->resourceKey.':'.$this->resourceId;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
