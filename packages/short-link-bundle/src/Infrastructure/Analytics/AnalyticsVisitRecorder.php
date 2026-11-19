<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\Analytics;

use Override;
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;

/**
 * Counts each visit as a server event of phil/analytics-bundle, under the rules of the analytics: the event is one the
 * site declares in `server_events`, and the dimensions sent — the code under `short_link`, the locale under `locale`
 * — count only when the site declares that dimension and that value. Only the visits of people are counted: a robot,
 * a prefetch or a link preview is left out, as the link's own count leaves it. Nothing of the visitor is sent.
 */
final readonly class AnalyticsVisitRecorder implements VisitRecorder
{
    public function __construct(
        private ServerEventCounter $counter,
        private string $site,
        private string $event = 'short_link_visit',
    ) {
    }

    #[Override]
    public function record(ShortLinkVisit $visit): void
    {
        if (!$visit->visitor->isHuman()) {
            return;
        }

        $dimensions = ['short_link' => $visit->code];

        if (null !== $visit->locale) {
            $dimensions['locale'] = $visit->locale;
        }

        $this->counter->count(SiteId::fromString($this->site), $this->event, $dimensions);
    }
}
