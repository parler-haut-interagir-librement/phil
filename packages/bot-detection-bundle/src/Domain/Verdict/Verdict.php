<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Domain\Verdict;

/**
 * What the browser reports, and the only thing it reports: robot or human.
 *
 * **A verdict is not an identifier.** It carries no fingerprint, no visitor id, no hash and no detector
 * detail, so it cannot single anyone out, and it is never stored, neither with the request it decides nor
 * with the session or its device fingerprint. It decides one request and is then dropped; only an aggregate
 * count of robot verdicts is kept (`RobotVerdictCounter`).
 *
 * **A verdict is forgeable.** It is computed by code running on the client, so a robot can send `human` and
 * a person's browser can be wrongly flagged. That is why a verdict only ever adds friction, never a refusal
 * (`FrictionPolicy`).
 */
enum Verdict: string
{
    case Human = 'human';
    case Robot = 'robot';

    /**
     * No usable verdict: the field was absent, empty, or held anything other than the two wire values. A
     * browser that blocks scripts, or a client that never ran them, lands here; so does a tampered value.
     * Whether this adds friction is the application's choice (`FrictionPolicy`).
     */
    case Unknown = 'unknown';

    /**
     * Reads the submitted value, strictly. Only the exact wire values `human` and `robot` are recognized;
     * anything else — another case, surrounding whitespace, a longer payload, `unknown` itself — is
     * `Unknown`. Parsing never throws: a verdict that fails to parse is no reason to refuse a request.
     */
    public static function fromSubmitted(?string $value): self
    {
        return match ($value) {
            self::Human->value => self::Human,
            self::Robot->value => self::Robot,
            default => self::Unknown,
        };
    }

    public function isRobot(): bool
    {
        return self::Robot === $this;
    }
}
