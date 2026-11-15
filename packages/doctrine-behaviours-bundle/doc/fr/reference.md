<!-- phil-dev source-sha256=5a1df1e11515daf5f492a2ce3f0c9e5d6dea11836f5c8f4f89ed4bf2751596aa -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle` | `AbstractBundle` | La configuration, les services, le filtre |
| `…\Infrastructure\DependencyInjection\BehaviourServices` | `final class` | Enregistre les écouteurs, les résolveurs, les lecteurs et les réponses de vie privée |
| `…\Infrastructure\Security\SecurityBlameResolver` | `BlameResolver` | L'utilisateur authentifié (celui qui en incarne un autre sous `switch_user`), par son identifiant ou par la méthode que nomme `blame.user_method` |
| `…\Infrastructure\Security\SecurityOwnerResolver` | `OwnerResolver` | L'utilisateur authentifié, le compte incarné sous `switch_user`, par la méthode que nomme `owner.user_method` |
| `…\Infrastructure\Privacy\BlamePrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | L'auteur dans les demandes d'accès et d'effacement de `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\OwnerPrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | Le propriétaire dans les demandes d'accès et d'effacement de `phil/privacy-bundle` |
| `…\Infrastructure\Security\SecurityVersionRestoreGuard` | `VersionRestoreGuard` | Le droit de revenir à une version passée, demandé à Symfony Security avec l'enregistrement comme sujet |

## Services

| Identifiant | Quand |
|---|---|
| `Phil\DoctrineBehaviours\TimestampListener` | `timestamps` |
| `Phil\DoctrineBehaviours\Contract\BlameResolver`, `Phil\DoctrineBehaviours\BlameListener`, `Phil\DoctrineBehaviours\BlameEraser` (public) | `blame` |
| `Phil\DoctrineBehaviours\SoftDeleteListener` | `soft_delete` |
| `Phil\DoctrineBehaviours\TreeListener` (étiqueté `kernel.reset`), `Phil\DoctrineBehaviours\Tree` (public) | `tree` |
| `Phil\DoctrineBehaviours\DeactivationListener` | `deactivation` |
| `Phil\DoctrineBehaviours\Contract\OwnerResolver`, `Phil\DoctrineBehaviours\OwnerListener`, `Phil\DoctrineBehaviours\OwnerEraser` (public) | `owner` |
| `Phil\DoctrineBehaviours\PublicationListener` | `publication` |
| `Phil\DoctrineBehaviours\PositionListener`, `Phil\DoctrineBehaviours\Positions` (public) | `position` |
| `Phil\DoctrineBehaviours\TranslationListener`, `Phil\DoctrineBehaviours\Translations` (public) | `translation` |
| `Phil\DoctrineBehaviours\SlugListener` | `slug` |
| `Phil\DoctrineBehaviours\Contract\VersionRestoreGuard`, `Phil\DoctrineBehaviours\VersionListener`, `Phil\DoctrineBehaviours\Versions` (public) | `versions` |
| `Phil\DoctrineBehaviours\DatedIdentifierListener` | `dated_identifier` |
| `…\Infrastructure\Privacy\BlamePrivacy`, étiqueté `phil_privacy.exporter` et `phil_privacy.eraser` | `privacy` et `blame`, avec `phil/privacy-bundle` installé |
| `…\Infrastructure\Privacy\OwnerPrivacy`, étiqueté `phil_privacy.exporter` et `phil_privacy.eraser` | `privacy` et `owner`, avec `phil/privacy-bundle` installé |

`BehaviourServices::FILTER` vaut `phil_soft_deleted`, et `BehaviourServices::DEACTIVATED_FILTER` vaut
`phil_deactivated`. Les comportements eux-mêmes sont ceux de `phil/doctrine-behaviours` : voir sa documentation.
