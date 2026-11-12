<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\ValueObject\CountryCode;
use SensitiveParameter;

/**
 * Derives the country of a client address, in memory.
 *
 * **The address is used and forgotten.** The adapter neither logs nor stores it, and answers
 * {@see CountryCode::unknown()} when it cannot tell, including when its database is past the age its licence
 * allows ({@see \Phil\AnalyticsBundle\Domain\Country\GeoDatabaseFreshness}).
 */
interface CountryResolver
{
    public function countryOf(#[SensitiveParameter] string $ip): CountryCode;
}
