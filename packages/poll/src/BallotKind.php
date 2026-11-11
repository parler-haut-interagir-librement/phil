<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * What a ballot of a poll holds: one option, several options, or options in the order of the voter's preference.
 */
enum BallotKind: string
{
    /** Exactly one option. */
    case Single = 'single';

    /** Between a minimum and a maximum of options, in no order. */
    case Multiple = 'multiple';

    /** Between a minimum and a maximum of options, the first one preferred. */
    case Ranked = 'ranked';
}
