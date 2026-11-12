<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Content;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * The kinds of content that have a single root — the home page, the contact details, the « about » page —, by resource
 * key: `phil_content.singles`. A single kind is created once, then only edited and published; `ContentManager::single()`
 * finds its root without an identifier.
 */
final readonly class SingleKinds
{
    /**
     * @param list<string> $resourceKeys
     */
    private function __construct(private array $resourceKeys)
    {
    }

    /**
     * @param array<array-key, mixed> $resourceKeys
     *
     * @throws InvalidContent when a key is not a resource key
     */
    public static function fromArray(array $resourceKeys): self
    {
        $keys = [];

        foreach ($resourceKeys as $resourceKey) {
            if (!\is_string($resourceKey) || !ContentReference::isResourceKey($resourceKey)) {
                throw InvalidContent::because(\sprintf('A single kind is a resource key, a lower-case letter then up to 63 lower-case letters, digits or "_"; "%s" is not.', \is_string($resourceKey) ? $resourceKey : get_debug_type($resourceKey)));
            }

            $keys[$resourceKey] = $resourceKey;
        }

        return new self(array_values($keys));
    }

    public function isSingle(string $resourceKey): bool
    {
        return \in_array($resourceKey, $this->resourceKeys, true);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->resourceKeys;
    }
}
