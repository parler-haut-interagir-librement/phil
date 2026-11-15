# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle` | `AbstractBundle` | The configuration, the services, the filter |
| `…\Infrastructure\DependencyInjection\BehaviourServices` | `final class` | Registers the listeners, the resolvers, the readers and the privacy answers |
| `…\Infrastructure\Security\SecurityBlameResolver` | `BlameResolver` | The authenticated user — the impersonator under `switch_user` —, by their identifier or the method `blame.user_method` names |
| `…\Infrastructure\Security\SecurityOwnerResolver` | `OwnerResolver` | The authenticated user, the account acted as under `switch_user`, by the method `owner.user_method` names |
| `…\Infrastructure\Privacy\BlamePrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | The blame in the access and erasure requests of `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\OwnerPrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | The owner in the access and erasure requests of `phil/privacy-bundle` |
| `…\Infrastructure\Security\SecurityVersionRestoreGuard` | `VersionRestoreGuard` | The right to go back to a past version, asked of Symfony Security with the record as subject |

## Services

| Id | When |
|---|---|
| `Phil\DoctrineBehaviours\TimestampListener` | `timestamps` |
| `Phil\DoctrineBehaviours\Contract\BlameResolver`, `Phil\DoctrineBehaviours\BlameListener`, `Phil\DoctrineBehaviours\BlameEraser` (public) | `blame` |
| `Phil\DoctrineBehaviours\SoftDeleteListener` | `soft_delete` |
| `Phil\DoctrineBehaviours\TreeListener` (tagged `kernel.reset`), `Phil\DoctrineBehaviours\Tree` (public) | `tree` |
| `Phil\DoctrineBehaviours\DeactivationListener` | `deactivation` |
| `Phil\DoctrineBehaviours\Contract\OwnerResolver`, `Phil\DoctrineBehaviours\OwnerListener`, `Phil\DoctrineBehaviours\OwnerEraser` (public) | `owner` |
| `Phil\DoctrineBehaviours\PublicationListener` | `publication` |
| `Phil\DoctrineBehaviours\PositionListener`, `Phil\DoctrineBehaviours\Positions` (public) | `position` |
| `Phil\DoctrineBehaviours\TranslationListener`, `Phil\DoctrineBehaviours\Translations` (public) | `translation` |
| `Phil\DoctrineBehaviours\SlugListener` | `slug` |
| `Phil\DoctrineBehaviours\Contract\VersionRestoreGuard`, `Phil\DoctrineBehaviours\VersionListener`, `Phil\DoctrineBehaviours\Versions` (public) | `versions` |
| `Phil\DoctrineBehaviours\DatedIdentifierListener` | `dated_identifier` |
| `…\Infrastructure\Privacy\BlamePrivacy`, tagged `phil_privacy.exporter` and `phil_privacy.eraser` | `privacy` and `blame`, with `phil/privacy-bundle` installed |
| `…\Infrastructure\Privacy\OwnerPrivacy`, tagged `phil_privacy.exporter` and `phil_privacy.eraser` | `privacy` and `owner`, with `phil/privacy-bundle` installed |

`BehaviourServices::FILTER` is `phil_soft_deleted`, and `BehaviourServices::DEACTIVATED_FILTER` is `phil_deactivated`.
The behaviours themselves are `phil/doctrine-behaviours`'s: see its documentation.
