<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Domain\ValueObject;

use Phil\HttpCacheTagsBundle\Domain\Exception\InvalidCacheTag;

/**
 * One tag of a cached response: `page:42`, `product-list`. Letters, digits, `_`, `.`, `:` and `-`, so that it fits
 * every proxy's header and needs no escaping; at most 128 bytes.
 */
final readonly class CacheTag
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        return self::tryOf($value) ?? throw InvalidCacheTag::because(\sprintf('A cache tag is 1 to 128 letters, digits, "_", ".", ":" or "-"; "%s" is not.', $value));
    }

    /**
     * The tag, or `null` when the text is not one.
     */
    public static function tryOf(string $value): ?self
    {
        return 1 === preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $value) ? new self($value) : null;
    }

    /**
     * @param iterable<string> $values
     *
     * @return list<self> each once, in the order given
     */
    public static function list(iterable $values): array
    {
        $tags = [];

        foreach ($values as $value) {
            // A repeated tag was checked the first time: it is not checked again.
            if (!\array_key_exists($value, $tags)) {
                $tags[$value] = self::of($value);
            }
        }

        return array_values($tags);
    }
}
