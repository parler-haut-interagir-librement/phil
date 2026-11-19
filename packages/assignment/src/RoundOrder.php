<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * The order of the waiting applicants in each round of `Mechanism::Rounds`.
 */
enum RoundOrder: string
{
    /** The draw order, in every round. */
    case Same = 'same';

    /** The draw order in odd rounds and its reverse in even rounds, so that the last served is served first next. */
    case Alternating = 'alternating';
}
