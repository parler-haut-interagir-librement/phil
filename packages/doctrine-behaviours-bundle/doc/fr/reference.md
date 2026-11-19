<!-- phil-dev source-sha256=beea0a15863a9103ed54e5d73aff136f0059e37c470a8937d0597307ae0d257c -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle` | `AbstractBundle` | La configuration, les services, le filtre |
| `…\Infrastructure\DependencyInjection\BehaviourServices` | `final class` | Enregistre les écouteurs, les résolveurs, les lecteurs et les réponses de vie privée |
| `…\Infrastructure\Security\SecurityBlameResolver` | `BlameResolver` | L'utilisateur authentifié (celui qui en incarne un autre sous `switch_user`), par son identifiant ou par la méthode que nomme `blame.user_method` |
| `…\Infrastructure\Security\SecurityOwnerResolver` | `OwnerResolver` | L'utilisateur authentifié, le compte incarné sous `switch_user`, par la méthode que nomme `owner.user_method` |
| `…\Infrastructure\Privacy\BlamePrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | L'auteur dans les demandes d'accès et d'effacement de `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\OwnerPrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | Le propriétaire dans les demandes d'accès et d'effacement de `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\VersionsPrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | Les champs personnels des versions figées dans les demandes d'accès et d'effacement de `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\PersonalDataFields` | `PersonalFields` | Les propriétés d'une classe déclarées `#[PersonalData]`, celles de ses parents comprises |
| `…\Infrastructure\Privacy\SuspendBlame`, `…\Infrastructure\Privacy\SuspendBlameWithExport` | `PersonalDataEraser`, et `PersonalDataExporter` | Un outil d'effacement de `phil/privacy-bundle` exécuté avec l'auteur suspendu |
| `…\Infrastructure\DependencyInjection\SuspendBlameDuringErasurePass` | passe de compilation | Décore chaque service étiqueté `phil_privacy.eraser` avec `SuspendBlame` quand `blame.suspend_during_erasure` est activé |
| `…\Infrastructure\Console\StripVersionsCommand` | commande de console `phil:doctrine-behaviours:strip-versions` | Les champs personnels retirés des versions déjà écrites, `--dry-run` pour les compter |
| `…\Infrastructure\Security\SecurityVersionRestoreGuard` | `VersionRestoreGuard` | Le droit de revenir à une version passée, demandé à Symfony Security avec l'enregistrement comme sujet |

## Services

| Identifiant | Quand |
|---|---|
| `Phil\DoctrineBehaviours\BinaryKeysListener`, sur `loadClassMetadata` ([configuration.md](configuration.md#collation-des-clés)) | toujours |
| `Phil\DoctrineBehaviours\TimestampListener` | `timestamps` |
| `Phil\DoctrineBehaviours\Contract\BlameResolver`, `Phil\DoctrineBehaviours\BlameListener`, `Phil\DoctrineBehaviours\BlameEraser` (public, sur le registre `doctrine`) | `blame` |
| `Phil\DoctrineBehaviours\BlameSuspension` (étiqueté `kernel.reset`) | `blame.suspend_during_erasure` et `privacy`, avec `phil/privacy-bundle` installé |
| `Phil\DoctrineBehaviours\SoftDeleteListener` (priorité `-256` sur `onFlush`), `Phil\DoctrineBehaviours\SoftDeletes` (public) | `soft_delete` |
| `Phil\DoctrineBehaviours\TreeListener` (étiqueté `kernel.reset`), `Phil\DoctrineBehaviours\Tree` (public) | `tree` |
| `Phil\DoctrineBehaviours\DeactivationListener` | `deactivation` |
| `Phil\DoctrineBehaviours\Contract\OwnerResolver`, `Phil\DoctrineBehaviours\OwnerListener`, `Phil\DoctrineBehaviours\OwnerEraser` (public, sur le registre `doctrine`) | `owner` |
| `Phil\DoctrineBehaviours\PublicationListener` | `publication` |
| `Phil\DoctrineBehaviours\PositionListener`, `Phil\DoctrineBehaviours\Positions` (public) | `position` |
| `Phil\DoctrineBehaviours\TranslationListener`, `Phil\DoctrineBehaviours\Translations` (public) | `translation` |
| `Phil\DoctrineBehaviours\SlugListener` | `slug` |
| `Phil\DoctrineBehaviours\Contract\VersionRestoreGuard`, `Phil\DoctrineBehaviours\VersionListener`, `Phil\DoctrineBehaviours\Contract\PersonalFields`, `Phil\DoctrineBehaviours\Versions` (public), `Phil\DoctrineBehaviours\VersionEraser` (public, sur le registre `doctrine`) | `versions` |
| `…\Infrastructure\Console\StripVersionsCommand`, étiqueté `console.command` | `versions`, avec `symfony/console` installé |
| `Phil\DoctrineBehaviours\DatedIdentifierListener` | `dated_identifier` |
| `…\Infrastructure\Privacy\BlamePrivacy`, étiqueté `phil_privacy.exporter` et `phil_privacy.eraser` | `privacy` et `blame`, avec `phil/privacy-bundle` installé |
| `…\Infrastructure\Privacy\OwnerPrivacy`, étiqueté `phil_privacy.exporter` et `phil_privacy.eraser` | `privacy` et `owner`, avec `phil/privacy-bundle` installé |
| `…\Infrastructure\Privacy\VersionsPrivacy`, étiqueté `phil_privacy.exporter` et `phil_privacy.eraser` | `privacy` et `versions`, avec `phil/privacy-bundle` installé |

`BehaviourServices::FILTER` vaut `phil_soft_deleted`, `BehaviourServices::DEACTIVATED_FILTER` vaut `phil_deactivated`, et
`BehaviourServices::SOFT_DELETE_PRIORITY` vaut `-256`. Les comportements eux-mêmes sont ceux de `phil/doctrine-behaviours` : voir sa documentation.
