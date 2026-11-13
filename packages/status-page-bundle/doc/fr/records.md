<!-- phil-dev source-sha256=cc0c7b98439689fdc9428b42c8570229a61dc036f7f88299061f6c354ab39570 -->
# Incidents et maintenances

## Le format

`RecordsReader` lit les mêmes données de la configuration et d'un fichier JSON :

```json
{
    "incidents": [
        {
            "id": "database-slow",
            "title": "Slow pages",
            "impact": "degraded",
            "components": ["website"],
            "updates": [
                { "at": "2026-11-12T10:00:00+00:00", "state": "investigating", "message": "Pages load slowly." },
                { "at": "2026-11-12T10:40:00Z", "state": "resolved", "message": "An index was rebuilt." }
            ]
        }
    ],
    "maintenances": [
        {
            "id": "upgrade",
            "title": "Database upgrade",
            "description": "The API answers in read-only mode.",
            "components": ["api"],
            "starts_at": "2026-11-20T02:00:00-05:00",
            "ends_at": "2026-11-20T03:00:00-05:00"
        }
    ]
}
```

| Champ | Règle |
|---|---|
| `id` | De 1 à 64 lettres minuscules, chiffres, `-` ou `_`, commençant par une lettre ou un chiffre ; une fois par liste. |
| `title` | Une ligne de 1 à 160 caractères, sans caractères de contrôle. |
| `impact` | `degraded`, `partial_outage` ou `major_outage`. |
| `components` | Des identifiants de composants, chacun une fois ; un identifiant que la page ne connaît pas est montré tel quel. |
| `updates` | De 1 à 100 ; leur ordre n'importe pas, la page les trie. |
| `at`, `starts_at`, `ends_at` | ISO 8601 avec secondes et décalage, `Z` ou `±hh:mm` ; une date que le calendrier n'a pas est refusée. |
| `state` | `investigating`, `identified`, `monitoring` ou `resolved`. |
| `message`, `description` | De 1 à 4 000 caractères ; les sauts de ligne sont conservés, les autres caractères de contrôle refusés. `description` est facultative. |

Un incident commence avec sa plus ancienne mise à jour et se tient là où sa plus récente le dit : il est en cours
jusqu'à ce qu'une mise à jour dise `resolved`, puis il est dans l'historique pendant `history_days` jours. Une
maintenance est à venir avant `starts_at`, en cours jusqu'à `ends_at`, qui suit `starts_at`. Une liste contient 500
enregistrements au plus, et une clé qui n'est pas dans le tableau est refusée. Un refus est une `InvalidStatusRecord`
qui nomme l'enregistrement et le champ, jamais la valeur.

## Où ils sont conservés

| Option | Magasin | Lu |
|---|---|---|
| `records.service` | Un `StatusRecords` de l'application : une table, une interface d'administration. | À chaque page. |
| `records.file` | `JsonFileRecords` : un fichier JSON de 1 Mio au plus, écrit par les opérateurs, un déploiement ou une autre machine ; l'absence de fichier ne contient rien. | À chaque page. |
| `records.incidents`, `records.maintenances` | `ConfiguredRecords` : la configuration, vérifiée à la construction du conteneur. | Une fois. |

Avec un fichier, un nouvel incident apparaît à la page suivante et à l'instantané suivant, sans déploiement.
