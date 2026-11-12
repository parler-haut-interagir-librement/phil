<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Collection;

use Override;
use Phil\AnalyticsBundle\Application\Contract\CountryResolver;
use Phil\AnalyticsBundle\Domain\ValueObject\CountryCode;
use SensitiveParameter;

/**
 * No geolocation database: every page view is counted under the unknown country. An application with a database
 * replaces this adapter.
 */
final readonly class UnknownCountryResolver implements CountryResolver
{
    #[Override]
    public function countryOf(#[SensitiveParameter] string $ip): CountryCode
    {
        return CountryCode::unknown();
    }
}
