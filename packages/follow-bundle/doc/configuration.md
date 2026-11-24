# Configuration

The bundle's configuration lives under `phil_follow`. Every option has a default; with Symfony Security, `access` is
required.

```yaml
# config/packages/phil_follow.yaml
phil_follow:
    connection: default               # the Doctrine connection of the tables
    member_subject_type: member       # the subject type that names a member
    batch_size: 500                   # followers served by one message, 1 to 1000
    message_bus: null                 # the bus of FanOutActivity; messenger.default_bus when null
    current_member: null              # a CurrentRecipient service; phil_notifications' one when null
    access: App\Follow\SubjectAccess  # a FollowAccess service; required with Symfony Security
    approval: App\Follow\LockedProfiles   # a FollowApproval service; every subject open when null
    parents: App\Follow\ForumParents  # a SubjectParents service; no parents when null
    inheritance:
        max_depth: 5                  # parents read, nearest first, 0 to 16
    events: [reply.created, poll.opened]  # the kinds of event offered at the custom level
    auto_follow:
        created: false                # AutoFollow::created() follows what a member creates
        replied: false                # AutoFollow::replied() follows what a member replies to
        level: watching               # watching or mentions
    mutual:
        enabled: false                # mutual relationships between members
        follow_each_other: true       # an accepted relationship makes each follow the other
    followers:
        hidden_by_default: false      # whether a member who never chose hides their followers
    email:
        once_until_seen: true         # one notification on every channel until the member sees the subject
        resend_after: 604800          # seconds unseen after which it goes out on every channel again
    notifications:
        declare_types: true           # declare the notification types in phil_notifications.types
        activity: follow.activity
        quiet: follow.activity_quiet
        requested: follow.requested
        accepted: follow.accepted
        followed: follow.followed
    retention:
        activity_days: 30             # days an activity and the claims of its deliveries are kept
    schedule:
        enabled: true                 # the schedule phil_follow, with Symfony Scheduler
        at: '04:15'
        timezone: UTC
        lock_factory: lock.factory    # keeps the schedule to one worker; used when it exists
        state_pool: cache.app         # keeps the last run; used when it exists
```

| Option | Default | What it does |
|---|---|---|
| `connection` | `default` | The Doctrine connection of the tables. |
| `member_subject_type` | `member` | The subject type whose identifier is a member: following it follows a person, who is told, may lock their profile and hide their followers. |
| `batch_size` | `500` | How many followers one `FanOutActivity` message serves, 1 to 1000, the most a notification names. |
| `message_bus` | `null` | The bus of the `FanOutActivity` messages; `messenger.default_bus` when it exists, none otherwise, the pages then following each other at once. |
| `current_member` | `null` | The service that names the current member, a `CurrentRecipient` of phil/notifications-bundle; that bundle's own when null. |
| `access` | `null` | The `FollowAccess` service. Required with Symfony Security; `Phil\FollowBundle\Application\OpenAccess` opens every subject to every member. |
| `approval` | `null` | The `FollowApproval` service, which names the approver of a locked subject; every subject is open when null. |
| `parents` | `null` | The `SubjectParents` service; no subject has parents when null. |
| `inheritance.max_depth` | `5` | How many parents of a subject are read, 0 to 16. |
| `events` | `[]` | The kinds of event the templates offer a level for at the custom level. |
| `auto_follow.created`, `auto_follow.replied` | `false` | Whether `AutoFollow` follows what a member creates, or replies to. |
| `auto_follow.level` | `watching` | The level of an automatic follow, `watching` or `mentions`. |
| `mutual.enabled` | `false` | Whether members may join in mutual relationships. |
| `mutual.follow_each_other` | `true` | Whether an accepted relationship makes each member follow the other, unless they chose a level already. |
| `followers.hidden_by_default` | `false` | Whether a member who never chose hides their followers. |
| `email.once_until_seen` | `true` | Whether, after a notification sent on every channel, the next activities of the subject stay in the centre until the member sees the subject. |
| `email.resend_after` | `604800` | After how many seconds unseen a notification goes out on every channel again. |
| `notifications.declare_types` | `true` | Whether the bundle declares its five types under their default keys in `phil_notifications.types`. |
| `notifications.*` | `follow.*` | The keys of the notification types the bundle sends. A type renamed is declared by the application. |
| `retention.activity_days` | `30` | How many days an activity and the claims of its deliveries are kept. |
| `schedule.enabled` | `true` | Whether the schedule `phil_follow` purges every day, when Symfony Scheduler is installed. |
| `schedule.at`, `schedule.timezone` | `04:15`, `UTC` | When the purge runs. |
| `schedule.lock_factory` | `lock.factory` | The lock factory that keeps the schedule to one worker, used when the service exists; null for none. |
| `schedule.state_pool` | `cache.app` | The cache pool that keeps the last run of the schedule, used when it exists; null for none. |

## The notification types

With `notifications.declare_types`, the bundle declares in `phil_notifications.types`:

| Type | Channels | Sent when |
|---|---|---|
| `follow.activity` | `site`, `email`, collapsed | An activity reaches a follower |
| `follow.activity_quiet` | `site`, collapsed, no toast | An activity reaches a follower who has not seen the subject since the last `follow.activity` |
| `follow.requested` | `site`, `email` | A request waits for its approver |
| `follow.accepted` | `site` | A request is accepted |
| `follow.followed` | `site` | A member gains a follower |

The application's own `phil_notifications.types` entries of the same keys change them: their channels, their
importance, whether email waits for the digest. The titles and bodies are translated in the `phil_notifications`
domain, in English and French by the bundle.
