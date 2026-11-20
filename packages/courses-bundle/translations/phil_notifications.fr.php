<?php

declare(strict_types=1);

/*
 * The notifications of the mandatory training, in the domain of phil/notifications-bundle: each template receives
 * %subject%, the course, and the data of the notification — %due%, %learner%, %cycle% and %assignment%.
 */
return [
    'courses.training.assigned.title' => 'Un cours vous est assigné : %subject%',
    'courses.training.assigned.body' => 'Terminez-le d’ici le %due%.',
    'courses.training.due_soon.title' => 'Échéance proche : %subject%',
    'courses.training.due_soon.body' => 'Terminez-le d’ici le %due%.',
    'courses.training.due.title' => 'Échéance aujourd’hui : %subject%',
    'courses.training.due.body' => 'Terminez-le aujourd’hui.',
    'courses.training.overdue.title' => 'En retard : %subject%',
    'courses.training.overdue.body' => 'L’échéance était le %due% : terminez-le dès que possible.',
    'courses.training.overdue_manager.title' => 'En retard pour %learner% : %subject%',
    'courses.training.overdue_manager.body' => 'L’échéance était le %due%.',
    'courses.training.renewed.title' => 'À renouveler : %subject%',
    'courses.training.renewed.body' => 'Terminez-le de nouveau d’ici le %due%.',
];
