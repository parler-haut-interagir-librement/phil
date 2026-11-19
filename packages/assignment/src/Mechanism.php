<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * How `RankedRounds` serves the ranked choices.
 */
enum Mechanism: string
{
    /**
     * Round after round: round 1 offers every waiting applicant their first choice, in draw order; round 2 their
     * second choice to those still waiting; and so on. A choice that was full when its round came is lost.
     */
    case Rounds = 'rounds';

    /**
     * One pass in draw order: each applicant takes their best choice that still has room.
     */
    case SerialDictatorship = 'serial-dictatorship';
}
