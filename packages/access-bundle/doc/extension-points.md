# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Name the member of a request another way | Implement `Application\Contract\CurrentMember` and name the service in `phil_access.current_member` |
| Keep the member viewed elsewhere than in the session | Alias `Application\Contract\ViewAsStore` to your service |
| Add kinds or relations to the model | Implement `Application\Contract\ModelContributor`; autoconfiguration tags it `phil_access.model_contributor` |
| Let an entity be checked | Implement `Application\Contract\AccessibleItem` |
| Keep the tuples elsewhere | Alias `Phil\ResourcePermissions\Contract\TupleStore` to your store |
| React to a membership or to "view as this member" | Listen to `MemberJoinedGroup`, `MemberLeftGroup`, `ViewAsStarted`, `ViewAsStopped` |
| Change the banner or the summary | Override their templates under `templates/bundles/PhilAccessBundle/components/` |
| Change the levels, groups, visibility by default, budget | The [configuration](configuration.md) |
