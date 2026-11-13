<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\Status;

/**
 * How a member comes into an access group. Each group declares the ones it accepts; any other is refused.
 */
enum EntryPoint: string
{
    /** A purchase gives the access: a product, an offer, a donation tier. */
    case Purchase = 'purchase';

    /** A personal invitation or an invitation link. */
    case Invitation = 'invitation';

    /** An administrator adds members, one or many at once. */
    case Bulk = 'bulk';

    /** A rule of the site adds the member: an automation, a workflow. */
    case Automation = 'automation';

    /** Every new member, from their first visit. */
    case Default = 'default';
}
