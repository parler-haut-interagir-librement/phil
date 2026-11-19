<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Contract;

use Phil\ShortLinkBundle\Application\ShortLinkVisit;

/**
 * Something told of each visit counted, after the response has gone: analytics, a log, a message for the
 * application. A visit carries identifiers only; a recorder that fails does not fail the others.
 */
interface VisitRecorder
{
    public function record(ShortLinkVisit $visit): void;
}
