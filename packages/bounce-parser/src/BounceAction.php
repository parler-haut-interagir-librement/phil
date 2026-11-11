<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * What the application should do with the address, as the parser or a rule decides it. The application maps each to
 * its own effects: a counter of consecutive bounces, a suppression list, an unsubscription, a queue for review.
 */
enum BounceAction: string
{
    /** Nothing: a success, an automatic reply, a report that the message was not spam. */
    case Ignore = 'ignore';
    /** Count a soft bounce against the address. */
    case CountSoft = 'count_soft';
    /** Count a hard bounce against the address. */
    case CountHard = 'count_hard';
    /** Stop sending to the address now, whatever the counters say. */
    case Suppress = 'suppress';
    /** Unsubscribe the address from what it was sent: a complaint. */
    case Unsubscribe = 'unsubscribe';
    /** Keep the message for a person to read. */
    case Review = 'review';

    /**
     * The action the parser takes for a kind, before any rule.
     */
    public static function for(BounceKind $kind): self
    {
        return match ($kind) {
            BounceKind::Hard => self::CountHard,
            BounceKind::Soft => self::CountSoft,
            BounceKind::AutoReply, BounceKind::Delivered => self::Ignore,
            BounceKind::Complaint => self::Unsubscribe,
            BounceKind::Feedback, BounceKind::Unknown => self::Review,
        };
    }
}
