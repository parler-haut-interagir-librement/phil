<?php

declare(strict_types=1);

/*
 * The notifications of the mandatory training, in the domain of phil/notifications-bundle: each template receives
 * %subject%, the course, and the data of the notification — %due%, %learner%, %cycle% and %assignment%.
 */
return [
    'courses.training.assigned.title' => 'A course is assigned to you: %subject%',
    'courses.training.assigned.body' => 'Complete it by %due%.',
    'courses.training.due_soon.title' => 'Due soon: %subject%',
    'courses.training.due_soon.body' => 'Complete it by %due%.',
    'courses.training.due.title' => 'Due today: %subject%',
    'courses.training.due.body' => 'Complete it today.',
    'courses.training.overdue.title' => 'Overdue: %subject%',
    'courses.training.overdue.body' => 'It was due on %due%: complete it as soon as you can.',
    'courses.training.overdue_manager.title' => 'Overdue for %learner%: %subject%',
    'courses.training.overdue_manager.body' => 'It was due on %due%.',
    'courses.training.renewed.title' => 'Time to renew: %subject%',
    'courses.training.renewed.body' => 'Complete it again by %due%.',
];
