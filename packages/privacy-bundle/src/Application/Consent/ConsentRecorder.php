<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Consent;

use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Psr\Clock\ClockInterface;

/**
 * Turns what a person answered into a dated choice on the current version of the text, and records its proof.
 *
 * Only declared optional categories can be granted, and nothing can be granted by a browser that sends the Global
 * Privacy Control.
 */
final readonly class ConsentRecorder
{
    public function __construct(
        private ConsentPolicy $policy,
        private ConsentProofs $proofs,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $categories the names the person granted
     */
    public function decide(array $categories, bool $globalPrivacyControl): ConsentChoice
    {
        $choice = new ConsentChoice(
            sodium_bin2hex(random_bytes(16)),
            $this->policy->version,
            $globalPrivacyControl ? [] : $this->policy->retain($categories),
            $this->clock->now(),
            $globalPrivacyControl,
        );

        $this->proofs->record($choice);

        return $choice;
    }
}
