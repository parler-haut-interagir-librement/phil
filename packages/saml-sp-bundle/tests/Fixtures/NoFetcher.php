<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Fixtures;

use Override;
use Phil\SamlSp\Contract\MetadataFetcher;
use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\Url\Url;

/**
 * A fetcher that reaches nothing: the tests give their metadata as XML.
 */
final class NoFetcher implements MetadataFetcher
{
    #[Override]
    public function fetch(Url $url): string
    {
        throw MetadataUnavailable::because(\sprintf('The tests fetch nothing; "%s" was asked.', $url->toString()));
    }
}
