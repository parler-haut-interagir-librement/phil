# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Apply a bounce decision to your records | Listen to `BounceDecided`; in a worker, handle `BounceDecidedMessage` |
| Pick the policy of the current site | Implement `CurrentSite` and name its service in `current_site` |
| Ask the DNS another way | Implement `MailDomainResolver` of `phil/email-address` and name its service in `dns.resolver` |
| Read another mailbox, such as IMAP, or a provider's API | Implement `BounceMailbox` and name its service in `bounces.mailbox` |
| Vouch for abuse reports another way | Implement `ReportAuthenticator` of `phil/bounce-parser` and name its service in `bounces.authenticator` |
| Keep the bounce history elsewhere | Alias `BounceHistoryRepository` to your own service |
| Add rules to the bounce parser | Replace the service `Phil\BounceParser\BounceParser`, built with your `Rules` |
| React to what the browser found | Listen to the controller's `hint`, `applied` and `failed` events |

The bundle collects no tagged services of its own. `MailerEventConsumer` is tagged `remote_event.consumer` once per
name of `bounces.remote_event_consumers`; `ForwardBounceDecision` listens to `BounceDecided` when `bounces.message_bus`
is set.

A `BounceHistoryRepository` must claim a key once while its claim lasts, count a subject's soft bounces within the
window it is given, and forget them on demand; the bundle's tests of `CacheBounceHistoryRepository` show what each
method answers.
