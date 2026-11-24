# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\FollowBundle\PhilFollowBundle` | The bundle: its configuration `phil_follow`, its notification types and its services. |
| `Application\Follows` | Follow, change the level, stop following, the state, the member's follows, seen. |
| `Application\FollowRequests` | The requests: list, accept, reject, withdraw. |
| `Application\Mutuals` | The mutual relationships. |
| `Application\Followers` | The followers of a subject as a viewer may see them, and the choice to hide them. |
| `Application\AutoFollow` | Following what a member creates or replies to. |
| `Application\Activities` | Publishing an activity. |
| `Application\FollowState` | A member's own follow of a subject, the inherited one, and a waiting request. |
| `Application\FollowerList` | A page of followers, or nothing when hidden. |
| `Application\FollowOutcome` | `Done` or `Requested`. |
| `Application\FollowTypes` | The keys of the notification types the bundle sends. |
| `Application\SubjectChain` | A subject and its parents, nearest first, bounded. |
| `Application\RecipientResolver` | Who among a page of follows hears of an activity. |
| `Application\NoParents`, `OpenFollowing`, `OpenAccess` | The defaults of the ports. |
| `Application\Contract\FollowAccess`, `FollowApproval`, `SubjectParents` | The ports the application writes. |
| `Application\Message\FanOutActivity`, `PurgeFollowRecords` | The messages. |
| `Application\Handler\FanOutActivityHandler`, `PurgeFollowRecordsHandler` | Their handlers. |
| `Application\Exception\FollowRefused`, `UnknownFollowRequest` | A change refused; a request that does not wait. |
| `Domain\Model\Follow`, `FollowRequest`, `Activity` | The records. |
| `Domain\Status\FollowLevel`, `RequestKind` | The levels; follow or mutual. |
| `Domain\ValueObject\EventPreferences`, `MemberPair` | The levels by event; two members in byte order. |
| `Domain\Identifier` | The shapes of identifiers, keys and subjects. |
| `Domain\Exception\FollowException`, `InvalidFollow` | Every exception of the bundle; a value of the wrong shape. |
| `Domain\Contract\*Repository` | The stores of follows, requests, relationships, activities, deliveries and visibility. |
| `Infrastructure\Doctrine\Dbal*Repository` | Their DBAL adapters. |
| `Infrastructure\Doctrine\FollowTables`, `FollowSchemaListener` | The tables, and their place in the ORM's schema. |
| `Infrastructure\Http\FollowController`, `FollowGuard` | The forms, and their checks. |
| `Infrastructure\Twig\FollowExtension` | The Twig functions. |
| `Infrastructure\Privacy\FollowPrivacy` | The export and the erasure. |
| `Infrastructure\Scheduler\FollowSchedule` | The schedule `phil_follow`. |
