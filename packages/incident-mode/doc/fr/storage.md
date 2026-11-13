<!-- phil-dev source-sha256=f2a4e65ce3f587e0496a46c7f1bf663399d5a4abfe7560a48cf86c2933ef9a95 -->
# Stockage

`Contract\ActivationStore` conserve les activations. Deux magasins sont fournis avec la bibliothèque.

| Magasin | Pour |
|---|---|
| `InMemoryActivationStore` | Les tests et les exécutions uniques ; `reset()` le vide |
| `Dbal\DbalActivationStore` | Une connexion Doctrine DBAL sur PostgreSQL, MySQL, MariaDB ou SQLite |

## La table

`Dbal\IncidentTables::ACTIVATIONS`, `phil_incident_activation`, contient une ligne par activation :

| Colonne | Type | Contient |
|---|---|---|
| `id` | `CHAR(32)`, clé primaire | L'identifiant aléatoire |
| `preset` | `VARCHAR(64)` | Le nom du préréglage |
| `restrictions` | `TEXT` | Les noms des restrictions, joints par des virgules |
| `started_at`, `ends_at` | `datetime_utc_immutable` | Le début et la fin |
| `started_by` | `VARCHAR(190)`, nul | L'acteur qui l'a déclenchée |
| `reason` | `TEXT`, nul | Pourquoi |
| `lifted_at` | `datetime_utc_immutable`, nul | Quand elle a été levée, ou sa fin quand elle est arrivée à échéance |
| `lifted_by` | `VARCHAR(190)`, nul | L'acteur qui l'a levée |
| `lift_reason` | `VARCHAR(16)`, nul | `manual` ou `expired` |

Les moments sont écrits et lus en UTC, de sorte que deux serveurs dans des fuseaux horaires différents lisent la même
fin. L'index `idx_phil_incident_in_force` sur (`lifted_at`, `ends_at`) répond à la question de chaque vérification, et
`idx_phil_incident_started` au journal.

`IncidentTables::configure($schema)` ajoute la table à un schéma qui en est dépourvu ; `Dbal\IncidentSchemaListener`,
sur l'événement `postGenerateSchema` de l'ORM, l'ajoute au schéma que l'ORM génère pour la connexion du magasin, de
sorte que `doctrine:migrations:diff` écrit la migration qui la crée.

## Concurrence

`lift()` et `extend()` sont chacune un seul `UPDATE … WHERE lifted_at IS NULL`, et disent si elles ont changé la ligne.
Quand deux workers lèvent le même incident, l'un des deux réussit ; `IncidentMode` n'envoie l'événement que pour lui.

Une ligne qui ne se lit plus — modifiée à la main en une restriction, un acteur ou une raison que la bibliothèque
refuse — est écartée de chaque réponse plutôt que de faire échouer chaque vérification.

## Renseignements personnels

Les acteurs sont des renseignements personnels. `ofActor($actor)` donne les activations qu'un acteur a déclenchées ou
levées, pour une demande d'accès ; `forgetActor($actor)` retire l'acteur de ces activations, et les activations restent
au journal sans nom. `phil/moderation-bundle` répond aux deux par les contrats de `phil/privacy-bundle`.
