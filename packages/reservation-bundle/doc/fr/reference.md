<!-- phil-dev source-sha256=c9c09a906a2c36adfa6180f118cd39105ab9e26336fa641100927b0e124ef8d1 -->
# Référence

| Type | Rôle |
|---|---|
| `PhilReservationBundle` | Câble les services, déclare le mappage des entités, configure `phil_reservation` |
| `Domain\Model\BookableResource` | Une ressource et ce qui décide de ses réservations |
| `Domain\Model\Reservation` | Des unités d'une ressource réservées pour un intervalle |
| `Domain\Status\ReservationStatus` | Retenue, confirmée, annulée, expirée |
| `Domain\Contract\ResourceRepository` | Le port des ressources |
| `Domain\Contract\ReservationRepository` | Le port des réservations et du verrou de la ressource |
| `Domain\Event\Reservation*` | Les événements des changements, avec des identifiants seulement |
| `Domain\Exception\InvalidReservation` | Une valeur ou un changement refusé |
| `Application\Reservations` | Vérifie, retient, réserve, confirme, déplace, annule, liste les créneaux, fait expirer les retenues |
| `Application\Reminders` | Envoie les rappels dus |
| `Application\ReservationDraft` | Ce que remplit le formulaire de réservation |
| `Application\Contract\ReminderSender` | Le port qui envoie un rappel |
| `Application\Message\SendReservationReminder` | Le message Messenger d'un rappel |
| `Application\Exception\ReservationRefused` | Un refus, avec sa décision |
| `Application\Exception\NotFound` | Une ressource ou une réservation absente, ou une ressource inactive |
| `Infrastructure\Doctrine\DoctrineResourceRepository` | Les ressources avec Doctrine |
| `Infrastructure\Doctrine\DoctrineReservationRepository` | Les réservations et le verrou avec Doctrine |
| `Infrastructure\Form\ReservationType` | Le formulaire de réservation |
| `Infrastructure\Translation\ViolationMessages` | Les raisons dans la langue de la personne qui lit |
| `Infrastructure\Console\ExpireHoldsCommand` | `phil:reservation:expire-holds` |
| `Infrastructure\Console\SendRemindersCommand` | `phil:reservation:send-reminders` |
| `Infrastructure\Messenger\MessengerReminderSender` | Envoie chaque rappel sous forme de message |
| `Infrastructure\Calendar\ReservationItems` | Les réservations confirmées dans les vues du calendrier |
