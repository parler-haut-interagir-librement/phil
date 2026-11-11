<?php

declare(strict_types=1);

namespace Phil\Listings;

/**
 * Where a response to a listing stands.
 */
enum ResponseStatus: string
{
    /** Sent by the respondent, who consents to be put in touch; waiting for the author. */
    case Pending = 'pending';

    /** Accepted by the author: both consent, and the application may put them in touch. */
    case Accepted = 'accepted';

    /** Declined by the author. */
    case Declined = 'declined';

    /** Withdrawn by the respondent, before or after it was accepted. */
    case Withdrawn = 'withdrawn';
}
