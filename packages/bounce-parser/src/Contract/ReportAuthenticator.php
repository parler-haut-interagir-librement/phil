<?php

declare(strict_types=1);

namespace Phil\BounceParser\Contract;

use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\Mime\Part;

/**
 * Says whether an abuse report comes from whom it claims: a feedback loop the application subscribed to. Anyone can
 * write a report to the bounce mailbox, and a complaint unsubscribes; a report no authenticator vouches for goes to
 * review instead.
 */
interface ReportAuthenticator
{
    public function isAuthentic(Part $message, FeedbackReport $report): bool;
}
