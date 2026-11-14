<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * `hash` — the hexadecimal HMAC of the value under the run's salt: equal values stay equal within the copy, so a
 * column used for grouping or joining still works, and no value can be found again from a list of candidates.
 *
 * Options: `algorithm` (default `sha256`, any of `hash_hmac_algos()`), `length` to keep only the first characters.
 */
final readonly class HashAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        $algorithm = $context->options->string('algorithm', 'sha256');

        if (!\in_array($algorithm, hash_hmac_algos(), true)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a hash algorithm this PHP offers for HMAC.', $algorithm));
        }

        $digest = $context->digest($value, $algorithm);

        return substr($digest, 0, $context->options->int('length', \strlen($digest)));
    }
}
