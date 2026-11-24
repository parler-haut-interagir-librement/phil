<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application\Contract;

use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\MailingList;

/**
 * Renders the web version of a sent campaign, as the archive freezes it: HTML for a page, purified, that holds nothing
 * of a subscriber — no unsubscription or preference link, no token, no tracking pixel.
 */
interface WebVersionRenderer
{
    public function render(Campaign $campaign, ?MailingList $list): string;
}
