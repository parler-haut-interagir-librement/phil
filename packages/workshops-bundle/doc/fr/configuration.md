<!-- phil-dev source-sha256=cb29de4d787c5611c9ace49af09ef02daf560f4c669801411342cf909865861b -->
# Configuration

```yaml
# config/packages/phil_workshops.yaml
phil_workshops:
    max_seats: 10
    waitlist: true
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `max_seats` | `10` | Le plus de places qu'une inscription, ou une place sur une liste d'attente, prend : de 1 à 1 000. Une séance ne donne jamais non plus à une inscription plus de places que sa capacité |
| `waitlist` | `true` | Si chaque séance a une liste d'attente quand phil/waitlist-bundle est installé et activé ; `false` refuse une séance complète comme `full`, sans liste |

Les listes d'attente elles-mêmes — combien de temps une offre est tenue, quelles inscriptions en attente une politique
choisit, les notifications — se configurent sous `phil_waitlist`, les options de phil/waitlist-bundle.

Tout le reste appartient à chaque séance — sa plage, son fuseau horaire, sa capacité, sa fenêtre d'inscription : voir
[Séances et inscriptions](registrations.md).
