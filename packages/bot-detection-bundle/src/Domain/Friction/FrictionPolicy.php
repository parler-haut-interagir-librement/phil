<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Domain\Friction;

use Phil\BotDetectionBundle\Domain\Verdict\Verdict;

/**
 * Maps a verdict to friction.
 *
 * Fixed by design: a human verdict adds none, a robot verdict adds one step, and nothing refuses. The one
 * choice left to the application is the missing or malformed verdict: treating it as a robot slows down
 * clients that never ran the detection script, and also people whose browser blocks scripts; treating it as
 * a human lets a client skip the detection by not sending anything.
 *
 * A browser that resists fingerprinting — randomized or blanked signals, as some privacy-focused browsers
 * do — is not a robot. That is a property of the detectors in the browser, not of this policy: by the time a
 * verdict reaches the server, it is already robot or human.
 */
final readonly class FrictionPolicy
{
    private function __construct(
        public bool $unknownAddsFriction,
    ) {
    }

    /**
     * Only a robot verdict adds friction; a missing or malformed verdict passes like a human one.
     */
    public static function robotOnly(): self
    {
        return new self(false);
    }

    /**
     * A missing or malformed verdict adds friction like a robot verdict.
     */
    public static function robotOrUnknown(): self
    {
        return new self(true);
    }

    public function frictionFor(Verdict $verdict): Friction
    {
        return match ($verdict) {
            Verdict::Human => Friction::None,
            Verdict::Robot => Friction::Step,
            Verdict::Unknown => $this->unknownAddsFriction ? Friction::Step : Friction::None,
        };
    }
}
