<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Fixtures;

use Override;
use Phil\SamlSp\Contract\MetadataFetcher;
use Phil\Url\Url;

/**
 * A fetcher that answers the same metadata and counts its calls.
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

        return $this->xml;
    }
}
