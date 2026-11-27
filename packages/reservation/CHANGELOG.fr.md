<!-- phil-dev source-sha256=6c9488cbf22ca59097a43a81c1229d1999a96909633fdf3ab4b421ed0e9fa50e -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/reservation` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Interval`, un intervalle de temps lu à la seconde, sa fin exclue ; `Booking`, `BookingRequest`, `Violation`,
  `ViolationCode` et `Decision` ; `ReservationException`, `InvalidInterval` et `InvalidDefinition`.
- `Bookable` : capacité, marges qui s'additionnent, disponibilité, règles et fuseau horaire ; `check()` donne chaque
  raison pour laquelle une demande est refusée et les réservations qu'elle rencontre, `free()` les unités libres sur un
  intervalle, `slots()` les créneaux libres d'une période selon un pas qui repart à chaque minuit, bornés par
  `MAX_CANDIDATES` et `MAX_SLOTS`. `LoadProfile` dispose une fois les unités tenues et lit le sommet d'un intervalle par
  une recherche dichotomique.
- Le port `Rule` et ses règles : `MinimumNotice`, `MaximumAdvance`, `DurationLimits` et `SlotGrid`.
- Le port `Availability`, `AlwaysAvailable` et `WeeklyAvailability`, dont les plages qui se touchent forment un seul
  intervalle par-dessus minuit, avec les jours fermés, lue sur 1 000 jours au plus.
- `ReminderSchedule` : les décalages des rappels d'une réservation, leurs moments, et le rappel dû, un rappel manqué
  étant dépassé par le suivant.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
