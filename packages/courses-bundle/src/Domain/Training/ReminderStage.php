<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Training;

/**
 * The reminders of an assigned course, each sent once a cycle: in the days before the due moment, in its last day, and
 * once it passed.
 */
enum ReminderStage: string
{
    case BeforeDue = 'before_due';
    case AtDue = 'at_due';
    case Overdue = 'overdue';
}
