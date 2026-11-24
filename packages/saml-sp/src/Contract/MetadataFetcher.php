<?php

declare(strict_types=1);

namespace Phil\SamlSp\Contract;

use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\Url\Url;

/**
 * Fetches the metadata of an identity provider from its address.
 */
interface MetadataFetcher
{
    /**
     * @throws MetadataUnavailable when the address cannot be read
     */
    public function fetch(Url $url): string;
}
