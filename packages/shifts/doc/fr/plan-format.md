<!-- phil-dev source-sha256=1c5aa10cdd8c939eb842d5f0e74b49b96902dc1a88c217962fdb58e04db5e7b9 -->
# Format du plan

## Le plan d'un événement

Les quarts d'un événement circulent dans le format JSON `phil-shifts/1` — ce qu'un coordonnateur importe, ou duplique
d'une édition à la suivante :

```json
{
    "format": "phil-shifts/1",
    "shifts": [
        {
            "id": "desk-sat-am",
            "title": "Info desk",
            "kind": "fixed",
            "starts_at": "2026-11-28T09:00:00-05:00",
            "ends_at": "2026-11-28T12:00:00-05:00",
            "capacity": 2,
            "reserve": 1
        },
        {
            "id": "cloakroom-sat",
            "title": "Cloakroom",
            "kind": "duration",
            "starts_at": "2026-11-28T08:00:00-05:00",
            "ends_at": "2026-11-28T20:00:00-05:00",
            "minutes": 120,
            "capacity": 5,
            "mode": "interest",
            "critical": true,
            "weight": 150
        }
    ]
}
```

| Champ | Type | Valeur par défaut |
|---|---|---|
| `id`, `title` | chaîne | obligatoire |
| `kind` | `fixed`, `task` ou `duration` | obligatoire |
| `starts_at`, `ends_at` | instant RFC 3339 avec son décalage, tel que `2026-11-28T09:00:00Z` | obligatoire |
| `minutes` | nombre entier | la fenêtre, pour un quart fixe ; obligatoire autrement |
| `capacity`, `reserve`, `weight` | nombre entier | 1, 0, 100 |
| `mode` | `self_sign_up`, `assigned` ou `interest` | `self_sign_up` |
| `required_role` | chaîne | aucun |
| `critical`, `swap_approval` | `true` ou `false` | `false` |
| `location`, `description` | chaîne | vide |

`ShiftPlan::fromJson()` lit un document d'au plus 1 Mio, d'une profondeur de 16 et de 2 000 quarts, chaque identifiant
une seule fois ; chaque quart respecte les limites de `Shift`. Une faute lance `InvalidDocument`, dont le message nomme
le chemin du champ — tel que `$.shifts[3].starts_at: must be an instant such as 2026-11-28T09:00:00Z` —, jamais sa
valeur. Un instant doit exister : le 30 février, 24:00 ou un décalage de 24 heures sont refusés. `toJson()` écrit les
instants en UTC, et un plan relu s'écrit à l'identique.

## Les listes et les offres stockées

Un stockage garde un quart tel que l'écrit `ShiftFormat::toArray()`, ses places telles que les écrit
`RosterFormat::toArray()` et une offre telle que l'écrit `SwapFormat::toArray()`, et les relit avec le `fromArray()` de
chacun. Une place est son bénévole, son état — `confirmed`, `reserve` ou `interested` —, son instant et le coordonnateur
qui l'a affectée :

```json
[
    {"volunteer": "ada", "status": "confirmed", "at": "2026-11-20T15:00:00Z", "assigned_by": "grace"},
    {"volunteer": "cy", "status": "reserve", "at": "2026-11-20T15:10:00Z"}
]
```

Une liste relue respecte les limites de son quart : chaque bénévole une seule fois, pas plus de places, de réserve ou
d'intéressés qu'il n'en permet.
