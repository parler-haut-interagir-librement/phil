# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports written by the application

Each port is an interface of `Phil\FollowBundle\Application\Contract`, named in the configuration.

### `FollowAccess` (`access`)

| Method | Contract |
|---|---|
| `filter(SubjectReference $subject, list<string> $members): list<string>` | The members allowed to follow the subject and hear of its activity, in the order given; asked once per page of followers, so one query for the page |

`OpenAccess` allows everyone. Required with Symfony Security.

### `FollowApproval` (`approval`)

| Method | Contract |
|---|---|
| `approverOf(SubjectReference $subject, string $follower): ?string` | The member who approves a new follower, or null for an open subject |

`OpenFollowing`, the default, leaves every subject open.

### `SubjectParents` (`parents`)

| Method | Contract |
|---|---|
| `parentsOf(SubjectReference $subject): iterable<SubjectReference>` | The parents, nearest first; read up to `inheritance.max_depth` |

`NoParents`, the default, gives none.

### `CurrentRecipient` (`current_member`)

The `CurrentRecipient` of phil/notifications-bundle names the current member of the forms and templates; that bundle's
own when `current_member` is null.

## Services the application calls

| Service | What it does |
|---|---|
| `Follows` | `follow()`, `unfollow()`, `state()`, `following()`, `seen()` |
| `FollowRequests` | `pendingFor()`, `sentBy()`, `accept()`, `reject()`, `withdraw()` |
| `Mutuals` | `request()`, `end()`, `areMutual()`, `pending()`, `of()`, `enabled()` |
| `Followers` | `followersOf()`, `count()`, `isHidden()`, `followersHidden()`, `hideFollowers()` |
| `AutoFollow` | `created()`, `replied()` |
| `Activities` | `publish()` |

## Messages

| Message | Handler | Carries |
|---|---|---|
| `FanOutActivity` | `FanOutActivityHandler` | The identifier of the activity and the last member of the page before |
| `PurgeFollowRecords` | `PurgeFollowRecordsHandler` | Nothing |

## Integrations

| With | The bundle |
|---|---|
| phil/notifications-bundle | Sends through `Notifications`, declares its types, translates them |
| Doctrine ORM | Adds its tables to the generated schema |
| Symfony Messenger | Sends the pages by `FanOutActivity` messages |
| Symfony Scheduler | Purges every day, with a lock and a state when they exist |
| phil/privacy-bundle | Exports and erases a member's records |
| Twig | Registers its functions, templates and forms |
