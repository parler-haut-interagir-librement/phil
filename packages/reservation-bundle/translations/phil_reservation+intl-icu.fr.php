<?php

declare(strict_types=1);

return [
    'form.start' => 'Début',
    'form.end' => 'Fin',
    'form.quantity' => 'Combien',
    'form.order' => 'La fin vient après le début.',
    'violation.quantity' => '{max, plural, one {Une seule unité peut être réservée.} other {De 1 à # unités peuvent être réservées.}}',
    'violation.too_soon' => '{minutes, plural, =0 {Ce moment est passé.} one {Réservez au moins # minute à l’avance.} other {Réservez au moins # minutes à l’avance.}}',
    'violation.too_far_ahead' => '{minutes, plural, one {Réservez au plus # minute à l’avance.} other {Réservez au plus # minutes à l’avance.}}',
    'violation.too_short' => '{minutes, plural, one {Une réservation dure au moins # minute.} other {Une réservation dure au moins # minutes.}}',
    'violation.too_long' => '{minutes, plural, one {Une réservation dure au plus # minute.} other {Une réservation dure au plus # minutes.}}',
    'violation.off_grid' => '{minutes, plural, one {Les réservations commencent et finissent à la minute.} other {Les réservations commencent et finissent par pas de # minutes.}}',
    'violation.unavailable' => 'Ce moment est hors des heures d’ouverture.',
    'violation.full' => '{free, plural, =0 {Ce moment est déjà réservé.} one {Il ne reste qu’une unité à ce moment.} other {Il ne reste que # unités à ce moment.}}',
];
