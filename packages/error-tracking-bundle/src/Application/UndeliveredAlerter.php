<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

use Override;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;
use Psr\Log\LoggerInterface;

/**
 * The alerter of the degraded state: the alerts are on, but no destination was configured. Each alert it is given —
 * once per new group or regression, never per event — becomes a warning in the log that names the group by its
 * fingerprint, so that the missing destination is seen without the capture failing.
 */
final readonly class UndeliveredAlerter implements GroupAlerter
{
    public const string MESSAGE = 'An error alert was not sent: the error tracking has no alert destination. Name recipients or an audience, or configure a chatter transport.';

    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function alert(ErrorGroup $group, Occurrence $occurrence): void
    {
        $this->logger?->warning(self::MESSAGE, ['fingerprint' => $group->fingerprint, 'occurrence' => $occurrence->value]);
    }
}
