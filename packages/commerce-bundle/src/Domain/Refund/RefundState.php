<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Refund;

/**
 * Where a refund stands: granted by someone allowed to, then executed by someone allowed to, or withdrawn.
 */
enum RefundState: string
{
    /** Granted with its reason; no money moved yet. */
    case Granted = 'granted';

    /** Asked of the payment's processor, or recorded for an offline payment. */
    case Executed = 'executed';

    /** Given up before it was executed. */
    case Withdrawn = 'withdrawn';
}
