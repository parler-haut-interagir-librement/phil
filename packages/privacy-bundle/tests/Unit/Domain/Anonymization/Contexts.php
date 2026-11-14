<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizerOptions;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Contexts on a seeded random source and a fixed salt: the same call gives the same value on every run.
 */
final class Contexts
{
    /**
     * @param array<string, scalar|null> $options
     */
    public static function of(array $options = [], string $locale = 'fr_CA', int $seed = 1, string $salt = 'salt'): AnonymizationContext
    {
        return new AnonymizationContext(new AnonymizerOptions($options), LocalePacks::builtIn()->get($locale), new Randomizer(new Xoshiro256StarStar(hash('sha256', (string) $seed, true))), $salt);
    }
}
