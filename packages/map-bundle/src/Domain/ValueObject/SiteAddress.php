<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * An address a map links to or loads: an absolute address, read by phil/url, or a path of the site itself, which
 * starts with one `/` — `//host` and `/\host`, which a browser would open on another host, are refused.
 */
final readonly class SiteAddress
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidMap when the text is neither a path of the site nor an absolute address
     */
    public static function of(string $text, bool $allowLocalhost = false): self
    {
        if (str_starts_with($text, '/')) {
            if (\strlen($text) > Url::MAX_LENGTH || 1 === preg_match('#^/[/\\\\]|[\s\x00-\x1f\x7f]#', $text)) {
                throw InvalidMap::because(\sprintf('"%s" is not a path of the site.', $text));
            }

            return new self($text);
        }

        try {
            return new self(Url::fromString($text, $allowLocalhost)->toString());
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidMap::because(\sprintf('"%s" is neither a path of the site nor an address: %s', $text, $invalidUrl->getMessage()));
        }
    }
}
