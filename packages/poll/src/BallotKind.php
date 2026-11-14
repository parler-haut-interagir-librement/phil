<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * What a ballot of a poll holds: one option, several options, options in the order of the voter's preference, or one
 * mark on a scale.
 */
enum BallotKind: string
{
    /** Exactly one option. */
    case Single = 'single';

    /** Between a minimum and a maximum of options, in no order. */
    case Multiple = 'multiple';

    /** Between a minimum and a maximum of options, the first one preferred. */
    case Ranked = 'ranked';

    /** Exactly one mark on a bounded range of whole numbers, whose options are the marks from the lowest up. */
    case Scale = 'scale';
}
