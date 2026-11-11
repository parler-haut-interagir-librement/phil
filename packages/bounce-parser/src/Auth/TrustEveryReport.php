<?php

declare(strict_types=1);

namespace Phil\BounceParser\Auth;

use Override;
use Phil\BounceParser\Contract\ReportAuthenticator;
use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\Mime\Part;

/**
 * Vouches for every report: for a mailbox only the feedback loops can write to, its address known to nobody else.
 */
final readonly class TrustEveryReport implements ReportAuthenticator
{
    #[Override]
    public function isAuthentic(Part $message, FeedbackReport $report): bool
    {
        return true;
    }
}
