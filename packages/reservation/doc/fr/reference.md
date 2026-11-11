<!-- phil-dev source-sha256=6a5a0dfb2f29e6e2d9c0941afc51cd0acf8eb4a47375c0369275c68372f81d55 -->
# Référence

| Type | Rôle |
|---|---|
| `Interval` | Une plage de temps, fin exclue, lue à la seconde ; `from` et `until` sont ses horodatages Unix |
| `Booking` | Une réservation qui retient des unités d'une ressource pendant une plage |
| `BookingRequest` | Une réservation demandée : plage, moment de la demande, unités, réservation remplacée |
| `Bookable` | Une ressource : capacité, marges, disponibilité, règles, fuseau horaire ; vérifications, unités libres, créneaux |
| `Decision` | La réponse à une demande : ses violations et les réservations qu'elle croise |
| `Violation` | Une raison de refuser une demande, avec ses paramètres |
| `ViolationCode` | Les raisons, avec des valeurs de chaîne stables |
| `LoadProfile` | Les unités retenues à chaque moment, et le maximum d'une plage |
| `ReminderSchedule` | Les décalages des rappels d'une réservation, leurs moments et celui qui est dû |
| `Contract\Availability` | Le port des plages ouvertes d'une ressource |
| `Contract\Rule` | Le port d'une règle que suit une demande |
| `Availability\AlwaysAvailable` | Ouvert à tout moment |
| `Availability\WeeklyAvailability` | Ouvert par jour de la semaine selon une horloge, avec des jours fermés |
| `Rule\MinimumNotice` | Un préavis avant le début |
| `Rule\MaximumAdvance` | Une fenêtre de réservation glissante |
| `Rule\DurationLimits` | Une durée minimale et maximale |
| `Rule\SlotGrid` | Débuts et durées sur une grille de minutes |
| `Exception\ReservationException` | Le marqueur de chaque exception de la bibliothèque |
| `Exception\InvalidInterval` | Une plage impossible, ou plus longue que ce que lit la bibliothèque |
| `Exception\InvalidDefinition` | Une ressource, une règle, une réservation ou un calendrier défini avec une valeur qu'il ne peut pas prendre |
