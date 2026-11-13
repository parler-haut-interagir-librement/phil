# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\AccessBundle\PhilAccessBundle` | The bundle: its configuration `phil_access` and its services. |
| `Phil\AccessBundle\Domain\ValueObject\MemberId` | A member by a stable identifier, never an email address. |
| `Phil\AccessBundle\Domain\ValueObject\SharingLevels` | The sharing levels, the widest first. |
| `Phil\AccessBundle\Domain\ValueObject\AccessGroup` | A declared access group: entry points and duration. |
| `Phil\AccessBundle\Domain\Status\Visibility` | Everyone, members, admins, owner; the narrowest of two. |
| `Phil\AccessBundle\Domain\Status\EntryPoint` | Purchase, invitation, bulk, automation, default. |
| `Phil\AccessBundle\Domain\Exception\AccessException` | Every exception of the bundle. |
| `Phil\AccessBundle\Domain\Exception\InvalidAccess` | A value or a configuration against the rules. |
| `Phil\AccessBundle\Application\AccessModel` | Builds the access model from the configuration and the contributors. |
| `Phil\AccessBundle\Application\Items` | Owners, spaces and visibilities of items. Service. |
| `Phil\AccessBundle\Application\Sharing` | Shares with members and groups. Service. |
| `Phil\AccessBundle\Application\AccessGroups` | Groups and their memberships. Service. |
| `Phil\AccessBundle\Application\Spaces` | Members and admins of spaces. Service. |
| `Phil\AccessBundle\Application\ViewAs` | "View as this member". Service. |
| `Phil\AccessBundle\Application\ItemAccess` | Checks, explanations and listings for the request's subject. Service. |
| `Phil\AccessBundle\Application\AccessSubject` | Who the checks of the request are for. |
| `Phil\AccessBundle\Application\NoCurrentMember` | Nobody signed in, the default without SecurityBundle. |
| `Phil\AccessBundle\Application\Contract\CurrentMember` | The member of the request. |
| `Phil\AccessBundle\Application\Contract\ViewAsStore` | Where the member viewed is kept. |
| `Phil\AccessBundle\Application\Contract\AccessibleItem` | An object checked by the reference of its tuples. |
| `Phil\AccessBundle\Application\Contract\ModelContributor` | Adds kinds and relations to the model. |
| `Phil\AccessBundle\Application\View\Share` | A share: level, member or group, end. |
| `Phil\AccessBundle\Application\View\Membership` | A member of a group and the end of the membership. |
| `Phil\AccessBundle\Application\Event\MemberJoinedGroup` | A member came into a group. |
| `Phil\AccessBundle\Application\Event\MemberLeftGroup` | A member was taken out of a group. |
| `Phil\AccessBundle\Application\Event\ViewAsStarted` | An admin started viewing as a member. |
| `Phil\AccessBundle\Application\Event\ViewAsStopped` | An admin stopped viewing as a member. |
| `Phil\AccessBundle\Application\Exception\ViewAsRefused` | "View as this member" refused. |
| `Phil\AccessBundle\Infrastructure\Security\AccessVoter` | `access.<level>` and `access.view_as`. |
| `Phil\AccessBundle\Infrastructure\Security\TokenCurrentMember` | The member of the security token. |
| `Phil\AccessBundle\Infrastructure\EasyAdmin\EasyAdminAccessVoter` | `EA_ACCESS_ENTITY` and `EA_EXECUTE_ACTION`. |
| `Phil\AccessBundle\Infrastructure\Session\SessionViewAsStore` | The member viewed, in the session. |
| `Phil\AccessBundle\Infrastructure\Http\ViewAsController` | The forms of "view as this member". |
| `Phil\AccessBundle\Infrastructure\Twig\AccessExtension` | `access_can`, `access_visibility`, `access_viewing_as`. |
| `Phil\AccessBundle\Infrastructure\Twig\Components\ViewAsBannerComponent` | `PhilAccess:ViewAsBanner`. |
| `Phil\AccessBundle\Infrastructure\Twig\Components\AccessSummaryComponent` | `PhilAccess:AccessSummary`. |
| `Phil\AccessBundle\Infrastructure\Twig\KitComponents` | The names of the kit's recipes. |
| `Phil\AccessBundle\Infrastructure\Console\PurgeCommand` | `phil:access:purge`. |
| `Phil\AccessBundle\Infrastructure\DependencyInjection\AccessServices` | Registers the services. |
| `Phil\AccessBundle\Infrastructure\DependencyInjection\CsrfProtectionPass` | Stops a container without CSRF protection. |
| `Phil\AccessBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the recipes after the Toolkit's subdirectory. |
