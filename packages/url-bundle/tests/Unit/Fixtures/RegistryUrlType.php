<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit\Fixtures;

use Override;
use Phil\Url\Url;
use Phil\Url\UrlType;

/**
 * An application's own kinds, for the tests: a registry on its domain, and a page under a path of it.
 */
enum RegistryUrlType: string implements UrlType
{
    case Listing = 'listing';
    case Registry = 'registry';

    #[Override]
    public function accepts(Url $url): bool
    {
        return match ($this) {
            self::Listing => $url->isOn('registry.example') && 'listing' === ($url->segments()[0] ?? null),
            self::Registry => $url->isOn('registry.example'),
        };
    }
}
