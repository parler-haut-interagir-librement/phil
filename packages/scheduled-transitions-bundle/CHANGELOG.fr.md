<!-- phil-dev source-sha256=1f3ab7a2597b6a4417225e7500570143453a2ad38375ef631f9e7a789212829c -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/scheduled-transitions-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.2.1-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal` 4.6), and of each development
  requirement (`doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

## 0.2.0-alpha - 2026-11-18

Les clés des transitions planifiées se comparent octet par octet sur MySQL et MariaDB. Voyez [Mise à niveau depuis
0.1](doc/fr/installation.md#mise-à-niveau-depuis-01).

### Ajouté

- `Infrastructure\Doctrine\BinaryKeysListener`, sur l'événement `loadClassMetadata` de l'ORM, et ses `FIELDS` : les
  champs des entités du bundle qui se comparent octet par octet sur MySQL et MariaDB.

### Modifié

- Sur MySQL et MariaDB, `id`, `resource_kind`, `resource_id` et `transition` de `phil_scheduled_transition` prennent le
  jeu de caractères `utf8mb4` et la collation `utf8mb4_bin` dans le schéma que génère l'ORM, par
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL et SQLite gardent leur valeur par défaut, qui compare déjà
  les octets.
- `doctrine/dbal` est exigé, déjà exigé par `doctrine/orm` : `BinaryKeysListener` lit la plateforme de la connexion.

### Sécurité

- Sous la collation par défaut insensible à la casse de MySQL et de MariaDB, les transitions planifiées pour la
  ressource `Doc-A` étaient listées et annulées pour `doc-a`.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- L'entité `ScheduledTransition`, avec la colonne `claimed_until` ; l'enum `TransitionStatus` ; les exceptions
  `SchedulerException`, `InvalidSchedule` et `NoHandler` ; le port `ScheduledTransitionRepository`, qui réclame une
  transition due en une seule instruction et consigne un résultat même une fois le gestionnaire d'entités fermé. Les
  ressources sont des `ResourceReference` de `phil/resource`, une dépendance, dont une référence malformée lance
  `InvalidResource`.
- `Scheduler`, qui prend une `ClockInterface` obligatoire, réclame chaque transition avant de l'appliquer pour que des
  exécutions qui se chevauchent n'en appliquent jamais une deux fois, et arrête une exécution dont le handler a fermé le
  gestionnaire d'entités une fois la tentative enregistrée ; `TransitionHandler` et `RunReport`.
- `DoctrineScheduledTransitionRepository`, qui consigne un résultat par un seul `UPDATE` et détache la transition, pour
  qu'une exécution coûte une instruction par transition plutôt qu'un *flush* sur tout le lot.
- `PhilScheduledTransitionsBundle`, alias d'extension `phil_scheduled_transitions`, clés `frequency`, `batch`,
  `max_attempts` et `claim_for` ; il branche le service `clock` du conteneur.
- `WorkflowTransitionHandler`, `SubjectResolver` et `Subject`.
- `TransitionsSchedule`, `RunDueTransitions` et son handler.
- `RunTransitionsCommand`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
