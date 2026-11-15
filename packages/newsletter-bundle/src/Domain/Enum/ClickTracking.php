<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * How the clicks of a campaign are counted: not at all, in a total per link, the default, or per recipient, an
 * explicit choice.
 */
enum ClickTracking: string
{
    case Off = 'off';
    case Aggregate = 'aggregate';
    case PerRecipient = 'per_recipient';
}
