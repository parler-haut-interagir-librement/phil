<!-- phil-dev source-sha256=6ed3dcc76d4402d0852b9e3a7139b761c0d66999243d1306c76cff3bc82a24aa -->
# Configuration

La configuration du bundle se trouve sous `phil_waitlist` ; chaque option a une valeur par défaut, et le bundle n'a
besoin d'aucun fichier de configuration.

```yaml
# config/packages/phil_waitlist.yaml
phil_waitlist:
    offer_window: 172800        # two days
    max_places: 10
    policy: first_in_line
    batch: 100
    notifications:
        joined: ~
        offered: waitlist.offered
        accepted: ~
        lapsed: waitlist.lapsed
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `offer_window` | `172800` | Combien de temps une entrée conserve une offre, en secondes, pour les listes ouvertes sans fenêtre propre : de 60 (une minute) à 2 592 000 (trente jours). |
| `max_places` | `10` | Le plus grand nombre de places qu'une entrée demande : de 1 à 1 000. Au-delà, c'est refusé avec `RefusalReason::TooManyPlaces`. |
| `policy` | `first_in_line` | À quelles entrées en attente les places gardées sont offertes : `first_in_line` strictement par rang, `first_that_fits` en passant par-dessus celles qui en demandent davantage. Faites un alias de `OfferPolicy` vers un service à vous pour une autre politique. |
| `batch` | `100` | Le plus grand nombre d'entrées lues à la fois : entrées en attente quand des places se libèrent, offres et listes quand des offres expirent, entrées énumérées ; de 1 à 10 000. |
| `notifications.joined` | `null` | Le type de notification envoyé quand une entrée s'inscrit sur une liste ; `null` n'en envoie aucune. |
| `notifications.offered` | `waitlist.offered` | Le type de notification envoyé quand des places sont offertes à une entrée. |
| `notifications.accepted` | `null` | Le type de notification envoyé quand une entrée accepte son offre. |
| `notifications.lapsed` | `waitlist.lapsed` | Le type de notification envoyé quand l'offre d'une entrée expire. |

Les options `notifications` comptent seulement quand phil/notifications-bundle est activé, et un type n'envoie rien
tant qu'il n'est pas déclaré à ce bundle ; voyez [Notifications](notifications.md).

Une liste ouverte avec sa propre fenêtre la garde : `Waitlists::open($type, $id, offerWindow: 3600)`, ou plus tard
`Waitlists::changeOfferWindow()`, pour les offres faites à partir de ce moment.
