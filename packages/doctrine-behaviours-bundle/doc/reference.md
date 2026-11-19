# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle` | `AbstractBundle` | The configuration, the services, the filter |
| `…\Infrastructure\DependencyInjection\BehaviourServices` | `final class` | Registers the listeners, the resolvers, the readers and the privacy answers |
| `…\Infrastructure\Security\SecurityBlameResolver` | `BlameResolver` | The authenticated user — the impersonator under `switch_user` —, by their identifier or the method `blame.user_method` names |
| `…\Infrastructure\Security\SecurityOwnerResolver` | `OwnerResolver` | The authenticated user, the account acted as under `switch_user`, by the method `owner.user_method` names |
| `…\Infrastructure\Privacy\BlamePrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | The blame in the access and erasure requests of `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\OwnerPrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | The owner in the access and erasure requests of `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\VersionsPrivacy` | `PersonalDataExporter`, `PersonalDataEraser` | The personal fields of the frozen versions in the access and erasure requests of `phil/privacy-bundle` |
| `…\Infrastructure\Privacy\PersonalDataFields` | `PersonalFields` | The properties of a class declared `#[PersonalData]`, its parents' included |
| `…\Infrastructure\Privacy\SuspendBlame`, `…\Infrastructure\Privacy\SuspendBlameWithExport` | `PersonalDataEraser`, and `PersonalDataExporter` | An eraser of `phil/privacy-bundle` run with the blame suspended |
| `…\Infrastructure\DependencyInjection\SuspendBlameDuringErasurePass` | compiler pass | Decorates every service tagged `phil_privacy.eraser` with `SuspendBlame` when `blame.suspend_during_erasure` is on |
| `…\Infrastructure\Console\StripVersionsCommand` | console command `phil:doctrine-behaviours:strip-versions` | The personal fields taken out of the versions already written, `--dry-run` to count them |
| `…\Infrastructure\Security\SecurityVersionRestoreGuard` | `VersionRestoreGuard` | The right to go back to a past version, asked of Symfony Security with the record as subject |

## Services

| Id | When |
|---|---|
| `Phil\DoctrineBehaviours\BinaryKeysListener`, on `loadClassMetadata` ([configuration.md](configuration.md#collation-of-the-keys)) | always |
| `Phil\DoctrineBehaviours\TimestampListener` | `timestamps` |
| `Phil\DoctrineBehaviours\Contract\BlameResolver`, `Phil\DoctrineBehaviours\BlameListener`, `Phil\DoctrineBehaviours\BlameEraser` (public, on the `doctrine` registry) | `blame` |
| `Phil\DoctrineBehaviours\BlameSuspension` (tagged `kernel.reset`) | `blame.suspend_during_erasure` and `privacy`, with `phil/privacy-bundle` installed |
| `Phil\DoctrineBehaviours\SoftDeleteListener` (priority `-256` on `onFlush`), `Phil\DoctrineBehaviours\SoftDeletes` (public) | `soft_delete` |
| `Phil\DoctrineBehaviours\TreeListener` (tagged `kernel.reset`), `Phil\DoctrineBehaviours\Tree` (public) | `tree` |
| `Phil\DoctrineBehaviours\DeactivationListener` | `deactivation` |
| `Phil\DoctrineBehaviours\Contract\OwnerResolver`, `Phil\DoctrineBehaviours\OwnerListener`, `Phil\DoctrineBehaviours\OwnerEraser` (public, on the `doctrine` registry) | `owner` |
| `Phil\DoctrineBehaviours\PublicationListener` | `publication` |
| `Phil\DoctrineBehaviours\PositionListener`, `Phil\DoctrineBehaviours\Positions` (public) | `position` |
| `Phil\DoctrineBehaviours\TranslationListener`, `Phil\DoctrineBehaviours\Translations` (public) | `translation` |
| `Phil\DoctrineBehaviours\SlugListener` | `slug` |
| `Phil\DoctrineBehaviours\Contract\VersionRestoreGuard`, `Phil\DoctrineBehaviours\VersionListener`, `Phil\DoctrineBehaviours\Contract\PersonalFields`, `Phil\DoctrineBehaviours\Versions` (public), `Phil\DoctrineBehaviours\VersionEraser` (public, on the `doctrine` registry) | `versions` |
| `…\Infrastructure\Console\StripVersionsCommand`, tagged `console.command` | `versions`, with `symfony/console` installed |
| `Phil\DoctrineBehaviours\DatedIdentifierListener` | `dated_identifier` |
| `…\Infrastructure\Privacy\BlamePrivacy`, tagged `phil_privacy.exporter` and `phil_privacy.eraser` | `privacy` and `blame`, with `phil/privacy-bundle` installed |
| `…\Infrastructure\Privacy\OwnerPrivacy`, tagged `phil_privacy.exporter` and `phil_privacy.eraser` | `privacy` and `owner`, with `phil/privacy-bundle` installed |
| `…\Infrastructure\Privacy\VersionsPrivacy`, tagged `phil_privacy.exporter` and `phil_privacy.eraser` | `privacy` and `versions`, with `phil/privacy-bundle` installed |

`BehaviourServices::FILTER` is `phil_soft_deleted`, `BehaviourServices::DEACTIVATED_FILTER` is `phil_deactivated`, and
`BehaviourServices::SOFT_DELETE_PRIORITY` is `-256`.
The behaviours themselves are `phil/doctrine-behaviours`'s: see its documentation.
