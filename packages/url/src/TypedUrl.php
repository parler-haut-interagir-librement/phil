<?php

declare(strict_types=1);

namespace Phil\Url;

use Phil\Url\Exception\InvalidUrl;

/**
 * A web address and its kind, checked: a `TypedUrl` of `KnownUrlType::Instagram` is on Instagram.
 */
final readonly class TypedUrl
{
    private function __construct(
        public UrlType $type,
        public Url $url,
    ) {
    }

    public static function of(UrlType $type, Url|string $url): self
    {
        $url = $url instanceof Url ? $url : Url::fromString($url);

        if (!$type->accepts($url)) {
            throw InvalidUrl::notOfType($url->toString(), $type->name);
        }

        return new self($type, $url);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->url->equals($other->url);
    }
}
