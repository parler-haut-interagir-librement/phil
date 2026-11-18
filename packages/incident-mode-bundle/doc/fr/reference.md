<!-- phil-dev source-sha256=cc5c28d6d50c1b401b364202cea5c73b4f0c6ffe97d2d6602b606ea7448a7418 -->
# Référence

Chaque classe du bundle, avec son rôle. Les types de la bibliothèque — `IncidentMode`, `Preset`, `Activation`,
`Contract\EstablishedMembers` et les autres — se trouvent dans sa propre référence.

| Classe | Rôle |
|---|---|
| `PhilIncidentModeBundle` | Le bundle et sa configuration `phil_incident_mode` |
| `Attribute\SuspendedDuring` | Refuse un contrôleur tant qu'une restriction qu'il nomme s'applique |
| `Application\Checkpoint` | Si une restriction s'applique au membre de la requête, les suspensions, `guard()` |
| `Application\Suspension` | Une restriction qui s'applique, et le moment où elle cesse de s'appliquer |
| `Application\Exception\SuspendedByIncident` | Le refus : une erreur HTTP avec `Retry-After` |
| `Application\Contract\CurrentMember` | Le membre de la requête, par un identifiant stable |
| `Application\DurationInput` | Une durée telle qu'une personne l'écrit, et inversement |
| `Infrastructure\Security\SecurityCurrentMember` | L'identifiant de l'utilisateur de Symfony Security |
| `Infrastructure\Http\CheckpointListener` | Vérifie l'attribut et la valeur par défaut de route ; produit le rendu d'un refus |
| `Infrastructure\Http\SuspensionPage` | La page de refus, la page d'état, le fragment d'avis |
| `Infrastructure\Http\IncidentAdminController` | La page d'administration et ses formulaires |
| `Infrastructure\Http\AdminRequest` | La vérification CSRF sans état de l'administration |
| `Infrastructure\Form\SuspendedDuringTypeExtension` | L'option `suspended_during` de chaque formulaire |
| `Infrastructure\Translation\IncidentWording` | Ce qui est suspendu et jusqu'à quand, dans la langue du visiteur et le fuseau horaire configuré |
| `Infrastructure\Twig\IncidentModeExtension` | Les fonctions Twig `phil_incident_*` |
| `Infrastructure\Twig\KitComponents` | Les noms des recettes du kit |
| `Infrastructure\Console\StartIncidentCommand` | `phil:incident:start` |
| `Infrastructure\Console\ExtendIncidentCommand` | `phil:incident:extend` |
| `Infrastructure\Console\LiftIncidentCommand` | `phil:incident:lift`, et le balayage avec `--expired` |
| `Infrastructure\Console\ListIncidentsCommand` | `phil:incident:list`, le journal et les préréglages |
| `Infrastructure\Console\Moments` | Les moments des commandes, en UTC |
| `Infrastructure\Scheduler\IncidentSchedule` | Le balayage récurrent de Symfony Scheduler |
| `Infrastructure\Audit\AuditIncidents` | Les incidents écrits dans la piste de phil/audit-bundle |
| `Infrastructure\Privacy\IncidentPrivacy` | Les incidents d'un membre exportés et effacés par phil/privacy-bundle |
| `Infrastructure\DependencyInjection\IncidentModeServices` | Les services, à partir de la configuration |
| `Infrastructure\DependencyInjection\IncidentModePass` | La levée sans bus, les mots sans traducteur, le sous-répertoire du kit |

## Services

| Service | Classe |
|---|---|
| `Phil\IncidentMode\IncidentMode` | Celui de la bibliothèque, avec les préréglages configurés, le magasin, le `clock` et l'`event_dispatcher` |
| `Phil\IncidentMode\Contract\ActivationStore` | Alias de `Phil\IncidentMode\Dbal\DbalActivationStore` sur la connexion configurée |
| `Phil\IncidentMode\Contract\EstablishedMembers` | Alias de `established_members`, ou de `NoEstablishedMembers` |
| `Phil\IncidentMode\Contract\LiftScheduler` | Alias de `MessengerLiftScheduler`, ou de `NoLiftScheduler` sans bus |
| `Phil\IncidentModeBundle\Application\Contract\CurrentMember` | Alias de `current_member`, ou de `SecurityCurrentMember` |
| `Phil\IncidentModeBundle\Application\Checkpoint` | Les points de contrôle |

## Routes

| Route | Chemin dans son fichier |
|---|---|
| `phil_incident_mode_admin`, `_admin_start`, `_admin_extend`, `_admin_lift` | `config/routes/admin.php` |
| `phil_incident_mode_status`, `phil_incident_mode_notice` | `config/routes/status.php` |

## Gabarits

| Gabarit | Ce que c'est |
|---|---|
| `@PhilIncidentMode/layout.html.twig` | La mise en page par défaut |
| `@PhilIncidentMode/admin/incidents.html.twig` | La page d'administration |
| `@PhilIncidentMode/suspended.html.twig` | La page de refus |
| `@PhilIncidentMode/status.html.twig` | La page d'état |
| `@PhilIncidentMode/_notice.html.twig` | L'avis de ce qui est suspendu |
| `@PhilIncidentMode/_banner.html.twig` | La bannière de la mise en page d'un site |
