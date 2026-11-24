<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use Override;
use Phil\SamlSp\Contract\MetadataFetcher;
use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\Url\Url;

/**
 * A fetcher that answers the same text and counts its calls.
 */
final class CountingFetcher implements MetadataFetcher
{
    public int $calls = 0;

    public function __construct(private readonly string $xml)
    {
    }

    #[Override]
    public function fetch(Url $url): string
    {
        ++$this->calls;

        return '' === $this->xml ? throw MetadataUnavailable::because('Nothing to fetch.') : $this->xml;
    }
}
