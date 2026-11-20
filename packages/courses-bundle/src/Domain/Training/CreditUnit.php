<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Training;

/**
 * The unit of the credits a course grants: continuing education units, or hours.
 */
enum CreditUnit: string
{
    case Ceu = 'ceu';
    case Hours = 'hours';
}
