<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application;

use DateTimeImmutable;
use Phil\Lti\Service\Ags\Score;
use Phil\Url\Url;

/**
 * A score the tool sends to a line item of a platform, kept until it is sent: when it was saved, when the bus took its
 * message — null until then —, and when and why the platform refused it for good — null while it may still be sent.
 */
final readonly class PendingScore
{
    /**
     * The longest reason of a refusal kept, in bytes.
     */
    public const int MAX_REFUSAL = 1024;

    public function __construct(
        public string $id,
        public string $issuer,
        public string $clientId,
        public Url $lineItem,
        public Score $score,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $dispatchedAt = null,
        public ?DateTimeImmutable $refusedAt = null,
        public ?string $refusal = null,
    ) {
    }

    /**
     * The reason of a refusal as the stores keep it: valid UTF-8 of `MAX_REFUSAL` bytes at most.
     */
    public static function refusal(string $reason): string
    {
        return mb_strcut(mb_scrub($reason, 'UTF-8'), 0, self::MAX_REFUSAL, 'UTF-8');
    }
}
