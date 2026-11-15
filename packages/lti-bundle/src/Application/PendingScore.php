<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application;

use DateTimeImmutable;
use Phil\Lti\Service\Ags\Score;
use Phil\Url\Url;

/**
 * A score the tool sends to a line item of a platform, kept until it is sent.
 */
final readonly class PendingScore
{
    public function __construct(
        public string $id,
        public string $issuer,
        public string $clientId,
        public Url $lineItem,
        public Score $score,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
