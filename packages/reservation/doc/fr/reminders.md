<!-- phil-dev source-sha256=ffc78f1052cf830fa415f83202b475d1808b09da459e70631d243e82bd93e25e -->
# Rappels

```php
$schedule = new ReminderSchedule(1440, 60);   // a day before, then an hour before
$schedule->moments($booking->interval);        // the two moments, the earliest first
$schedule->due($booking->interval, $now, $lastSent);
```

Les décalages sont des minutes avant le début, de 1 à un an, au plus 20 ; chacun n'est gardé qu'une fois, le plus
éloigné en premier. Un décalage hors des bornes, ou plus de 20 décalages, lève `InvalidDefinition`.

`due()` donne le décalage du rappel à envoyer maintenant, ou `null` : celui qui est le plus proche du début et dont le
moment est venu, après le dernier envoyé, tant que la réservation n'a pas commencé. Un rappel dont le moment est passé
sans envoi est dépassé : une réservation faite une heure avant son début ne reçoit que le rappel de l'heure, et une
tâche en retard envoie un seul rappel, pas plusieurs. L'application garde le dernier décalage qu'elle a envoyé et le
repasse ; la bibliothèque ne garde rien.
