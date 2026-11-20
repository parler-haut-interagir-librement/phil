<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Training;

/**
 * What the learner, or the person who assigned a course, is told about an assigned course: assigned, due soon, due
 * today, overdue — to the learner and to the person who assigned it —, and renewed for a new cycle.
 */
enum TrainingNotice: string
{
    case Assigned = 'assigned';
    case DueSoon = 'due_soon';
    case Due = 'due';
    case Overdue = 'overdue';
    case OverdueManager = 'overdue_manager';
    case Renewed = 'renewed';

    public static function of(ReminderStage $stage): self
    {
        return match ($stage) {
            ReminderStage::BeforeDue => self::DueSoon,
            ReminderStage::AtDue => self::Due,
            ReminderStage::Overdue => self::Overdue,
        };
    }
}
