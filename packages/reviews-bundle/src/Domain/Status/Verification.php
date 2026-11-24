<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Status;

/**
 * What the application confirmed of the author when the review was written: they bought the product (`Purchase`) or
 * took part in the game or the event (`Attendance`). The page shows it as a mark beside the review.
 */
enum Verification: string
{
    case Purchase = 'purchase';
    case Attendance = 'attendance';
}
