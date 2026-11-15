<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Notifications;

use Override;
use Phil\NotificationsBundle\Application\Contract\AudienceProvider;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Application\Policies;

/**
 * The audience `phil_terms:<policy>`: the members who have not accepted the latest material version of the policy,
 * read when the notice is delivered rather than when it is sent.
 */
final readonly class OutstandingAudience implements AudienceProvider
{
    public const string PREFIX = 'phil_terms:';

    public function __construct(
        private Policies $policies,
        private Outstanding $outstanding,
    ) {
    }

    #[Override]
    public function supports(string $audience): bool
    {
        return str_starts_with($audience, self::PREFIX) && $this->policies->has(substr($audience, \strlen(self::PREFIX)));
    }

    #[Override]
    public function recipients(string $audience): iterable
    {
        return $this->outstanding->ofLatestMaterial(substr($audience, \strlen(self::PREFIX)));
    }
}
