<!-- phil-dev source-sha256=8f2c66bee96ccba2e236ca16f30643b00d0c55127e407fa4681be6ecdf438a3a -->
# Configuration

```yaml
# config/packages/phil_reservation.yaml
phil_reservation:
    batch: 500
    calendar: true
```

| Option | Valeur par défaut | Sens |
|---|---|---|
| `batch` | `500` | Le nombre maximal de réservations que lit chaque exécution des commandes, et qu'affiche le calendrier sur une période : de 1 à 10 000 |
| `calendar` | `true` | Indique si les réservations confirmées sont des éléments des vues de `phil/calendar-bundle`, quand il est installé |

Tout le reste appartient à chaque ressource : voir [Ressources](resources.md).
