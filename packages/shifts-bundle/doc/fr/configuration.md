<!-- phil-dev source-sha256=ba3589dc5280ae8b27c8dbc274f3f806da037301279c829571835b77e03bae1e -->
# Configuration

```yaml
phil_shifts:
    connection: default
    person: null
    allocator: null
    pages: true
    time_zone: UTC
    rest_minutes: 0
    roles:
        volunteer: null
        coordinator: ROLE_SHIFTS_COORDINATOR
    minors:
        birth_dates: null
        age_of_majority: 18
    absences:
        threshold: null
        window_days: 365
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `connection` | `default` | La connexion Doctrine des tables. |
| `person` | `null` | Le service qui donne la personne connectée et ses rôles, un `CurrentPerson` ; l'utilisateur de Symfony Security quand `null`. |
| `allocator` | `null` | Le service qui attribue les places parmi les intéressés, un `InterestAllocator` ; le `FairAllocator` quand `null`. |
| `pages` | `true` | Si le bundle enregistre ses pages et ses composants, avec Twig. |
| `time_zone` | `UTC` | Le fuseau horaire dans lequel les pages montrent les quarts et dans lequel le formulaire les lit, un identifiant de la base IANA tel que `America/Toronto`. |
| `rest_minutes` | `0` | Les minutes de repos d'un bénévole entre deux quarts fixes qu'il occupe, de 0 à 1 440. |
| `roles.volunteer` | `null` | Le rôle qu'une personne doit avoir pour être bénévole ; toute personne connectée quand `null`. |
| `roles.coordinator` | `ROLE_SHIFTS_COORDINATOR` | Le rôle des coordonnateurs, qui planifient les quarts, affectent les bénévoles, attribuent les places et approuvent les échanges. |
| `minors.birth_dates` | `null` | Le service qui donne les dates de naissance des bénévoles, un `BirthDates` ; personne n'est mineur quand `null`. |
| `minors.age_of_majority` | `18` | L'âge à partir duquel un bénévole rejoint un quart sans le consentement d'un tuteur, de 1 à 30. |
| `absences.threshold` | `null` | Le nombre d'absences non annoncées à partir duquel un bénévole ne rejoint plus un quart de lui-même, de 1 à 100 ; jamais quand `null`. |
| `absences.window_days` | `365` | Les jours pendant lesquels les absences comptent, de 1 à 3 650. |

[Mineurs et absences](minors-and-absences.md) décrit les deux règles.

Les rôles sont vérifiés par le gestionnaire de décision d'accès de Symfony, de sorte que la hiérarchie des rôles de
l'application compte :

```yaml
security:
    role_hierarchy:
        ROLE_ORGANIZER: [ROLE_SHIFTS_COORDINATOR]
```

Le rôle qu'exige un quart — premiers soins, caisse — est l'un des rôles de la personne connectée : avec Symfony Security,
les rôles de son jeton. Un `CurrentPerson` de l'application peut en donner d'autres, lus dans un dossier de formation.

## Ce que le bundle enregistre

| Service | Enregistré |
|---|---|
| Les dépôts, la transaction, `Conflicts`, `FairAllocator`, `Minors`, `Absences`, `Rosters`, `SwapBoard`, `ShiftsVoter` | toujours |
| L'écouteur de schéma | avec l'ORM |
| `ShiftType` | avec Symfony Forms |
| Les contrôleurs, la garde CSRF, la personne connectée | avec `pages` et TwigBundle |
| Les composants `PhilShifts:ShiftCard` et `PhilShifts:MyShifts` | avec `pages`, TwigBundle et Symfony UX Twig Component |
| Le *handler* de `AllocateInterest` | avec Messenger |
| `phil:shifts:import` | avec Console |
| L'exportateur et l'effaceur | avec phil/privacy-bundle |

Aucun service ne garde d'état entre les requêtes, de sorte que le bundle fonctionne dans un *worker*.
