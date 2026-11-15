# Extension points

The bundle is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Make content of a package reportable | Implement `Application\Contract\ModerationTarget`; autoconfiguration tags it `phil_moderation.target` |
| Moderate profiles | Give a service for `Application\Contract\MemberProfiles` |
| Find the current member another way | Alias `Application\Contract\CurrentMember` to your service |
| Decide who moderates another way | Alias `Application\Contract\CurrentModerator` to your service |
| Plug in the watched words | Alias `Application\Contract\WordScreen` to the adapter |
| Trust members to hide | Alias `Application\Contract\TrustedHiders` to the adapter of the trust levels |
| Spare established members from an incident | Alias `Phil\IncidentMode\Contract\EstablishedMembers` of `phil/incident-mode` to your service, and list the restrictions in `incident.spare_established` ([Incident mode](incident-mode.md#established-members)) |
| Hold content by another rule | Alias `Application\Contract\SandboxPolicy` to your service |
| React to the moderation | Listen to `ReportFiled`, `DecisionTaken`, `ContentHeld`, `ContentHiddenByMember` and `StatementIssued` |
| Tell a member of a statement of reasons | Name a type of `phil/notifications-bundle` in `statements.notification_type`, or listen to `StatementIssued` |
| Ground the reasons in the terms or the law | Configure `statements.grounds` |
| Change a page | Override the template under `templates/bundles/PhilModerationBundle/` |
| Change a label | Translate the key in the domain `phil_moderation` |

An alias declared in the application's `config/services.yaml` replaces the bundle's.
