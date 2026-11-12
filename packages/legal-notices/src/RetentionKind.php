<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

/**
 * The shape of a retention period: a fixed duration, until an event, with no set end, or not set yet.
 */
enum RetentionKind: string
{
    case Fixed = 'fixed';

    case UntilEvent = 'until_event';

    case Indefinite = 'indefinite';

    case NotYetFixed = 'not_yet_fixed';
}
