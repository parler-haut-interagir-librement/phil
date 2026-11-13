<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

use Override;
use Phil\PagedMedia\Gotenberg\Internal\Url;

/**
 * A timestamp authority known by its URL, such as one from the configuration.
 */
final readonly class TimestampAuthorityUrl implements TimestampAuthority
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = Url::public($url, 'A timestamp authority');
    }

    #[Override]
    public function url(): string
    {
        return $this->url;
    }
}
