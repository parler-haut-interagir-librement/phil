# Extension points

What an application implements, configures, replaces or relies on. Members, subjects and contexts are references of
`phil/resource`.

## Ports written by the application

### `MemberDirectory`

`Phil\MentionsBundle\Application\Contract\MemberDirectory` (`member_directory`, required) — the application's members.
Each method answers in one query.

| Method | Contract |
|---|---|
| `resolve(non-empty-list<string> $handles): iterable<Member>` | The members of these handles, compared without case; unknown handles left out |
| `members(non-empty-list<ResourceReference> $references): iterable<Member>` | The members of these references; deleted members left out |
| `search(string $query, int $limit): iterable<Member>` | The members a writer may mention whose handle or name begins with the query, best first |
| `maySee(ResourceReference $member, ResourceReference $content): bool` | Whether the member may see the content |

`Member` is a reference, a handle, a name, a profile address or none, and the identifier notifications go to, the
reference by default. The reference and the recipient are stable identifiers the application gives, `user:42`, never
email addresses: a `Member` whose reference or recipient holds `@` is refused with `InvalidMention`, and so is a
mention recorded for such a member or author.

## Ports with shipped adapters

### `MentionPreferences`

`Phil\MentionsBundle\Application\Contract\MentionPreferences` (`preferences`) — `SettingsMentionPreferences` with
`phil/settings-bundle`, `AlwaysNotify` otherwise.

| Method | Contract |
|---|---|
| `notifies(string $recipient): bool` | Whether the member wants to be notified of their mentions; fast |

### `MentionRepository`

`Phil\MentionsBundle\Domain\Contract\MentionRepository` (`store`) — `DoctrineMentionRepository` or
`InMemoryMentionRepository`.

| Method | Contract |
|---|---|
| `ofSubject(ResourceReference $subject): list<RecordedMention>` | Oldest first |
| `ofMember(ResourceReference $member, int $limit): list<RecordedMention>` | Latest first |
| `change(list<RecordedMention> $added, list<RecordedMention> $removed): list<RecordedMention>` | In one change: removes, then adds each mention whose member the subject does not mention yet, keeping the row found; returns the mentions added, in the order given |
| `forget(ResourceReference $subject): void` | Every mention of the subject |

## Services the application calls

| Service | Use |
|---|---|
| `Mentions` | `inText()`, `inDocument()`, `mentionedIn()`, `mentioning()`, `forget()` |
| `MentionRecorder` | `record()` with mentions found elsewhere |
| `TextMentions`, `UjoMentions` | Finding mentions without recording them, for a preview |
| `MentionLinker` | Linking handles outside Twig |
| `CommentMentions` | The mentions of `phil/comments-bundle` comments |

## Events

`MemberMentioned`, on the PSR-14 dispatcher, once per member newly mentioned in a subject: see
[Recording](recording.md#the-event).

## Configuration

`pattern`, `limits`, `notification_type` and `comments.markdown` — see [Configuration](configuration.md). The
notification type is configured further under `phil_notifications.types`.

## Templates, styles and translations

The bundle has no template. `mentions.css` is overridden by the application's own rules on `.mention` and
`.phil-mentions-menu`, or by PHIL UX's tokens. The `phil_notifications` and `phil_mentions` domains are overridden in
the application's translations.
