<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * Why someone left, as they chose it, kept without anything that names them.
 */
enum UnsubscribeReason: string
{
    case TooFrequent = 'too_frequent';
    case NotRelevant = 'not_relevant';
    case NeverSignedUp = 'never_signed_up';
    case Other = 'other';
}
