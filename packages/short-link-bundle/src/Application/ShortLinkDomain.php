<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

/**
 * A domain that serves short links, for one locale or for all of them.
 */
final readonly class ShortLinkDomain
{
    public function __construct(
        public string $host,
        public ?string $locale = null,
        public string $scheme = 'https',
    ) {
    }

    /**
     * The address of a code on the domain.
     */
    public function urlOf(string $code): string
    {
        return $this->scheme.'://'.$this->host.'/'.$code;
    }
}
