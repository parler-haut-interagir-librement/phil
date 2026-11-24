<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain;

/**
 * What a respondent answers for one proposed date: they can come, they can come if they must, or they cannot.
 */
enum Availability: string
{
    case Yes = 'yes';

    /** Available if no better date is found: counted after the yes answers to break a tie. */
    case IfNeedBe = 'if_need_be';

    case No = 'no';
}
