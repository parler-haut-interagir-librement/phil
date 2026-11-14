# Configuration

```yaml
# config/packages/phil_mentions.yaml
phil_mentions:
    store: doctrine                 # doctrine, or memory for tests
    entity_manager: default
    member_directory: ~             # required: a MemberDirectory service
    pattern: '[\p{L}\p{N}_](?:[\p{L}\p{N}_.\-]{0,62}[\p{L}\p{N}_])?'
    limits:
        per_text: 20                # members a text mentions at most, 1 to 1000
        text_length: 100000         # characters of a text read for mentions, 1 to 10000000
    notification_type: member.mentioned
    preferences: ~                  # a MentionPreferences service
    comments:
        markdown: false             # whether comments are Markdown
        record: true                # whether each comment written, edited or deleted is read for mentions
```

| Option | Default | Effect |
|---|---|---|
| `store` | `doctrine` | Where mentions are kept: the table `phil_mention`, or memory, emptied between two requests |
| `entity_manager` | `default` | The entity manager of the table |
| `member_directory` | required | The service id of the application's `MemberDirectory` |
| `pattern` | see above | What a handle looks like after `@`: a regular expression without delimiters nor anchors, checked when the container is built — see [Mentions](mentions.md#the-pattern) |
| `limits.per_text` | `20` | How many members one text mentions at most; the linker resolves as many handles |
| `limits.text_length` | `100000` | How many characters a text holds when it is read |
| `notification_type` | `member.mentioned` | The notification type, declared to `phil/notifications-bundle` with the channels `site` and `email`, and `web_push` when its Web Push is enabled |
| `preferences` | none | The service id of a `MentionPreferences`; without one, the setting of `phil/settings-bundle` when it is enabled, else every member is notified |
| `comments.markdown` | `false` | Whether `CommentMentions` reads comments as Markdown |
| `comments.record` | `true` | Whether `RecordCommentMentions` records the mentions of each comment as `phil/comments-bundle` writes, edits or deletes it; `false` for an application that calls `CommentMentions` itself |

## What the bundle configures

When the bundle is enabled beside them, its `prepend` adds:

| Bundle | Configuration |
|---|---|
| DoctrineBundle | The mapping of `Phil\MentionsBundle\Domain\Model` |
| `phil/notifications-bundle` | `types.<notification_type>.channels: [site, email]`, with `web_push` when `web_push` is enabled, which the application's own configuration of the type overrides |
| `phil/settings-bundle` | The setting `notifications.mentions`: a boolean, on by default, in the notifications section after `notifications.web_push`, labelled in the `phil_mentions` domain |
| `phil/block-editor-bundle` | `mention_search: Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch`, unless the application names its own |
| FrameworkBundle with AssetMapper | The controller under `@phil/mentions-bundle`, the stylesheet under `phil-mentions` |

## Services

| Service | Registered |
|---|---|
| `Phil\MentionsBundle\Domain\Contract\MentionRepository` | An alias, public, of `DoctrineMentionRepository` or `InMemoryMentionRepository` |
| `Phil\MentionsBundle\Application\Contract\MemberDirectory` | An alias of `member_directory` |
| `Phil\MentionsBundle\Domain\ValueObject\MentionPattern` | From `pattern` |
| `Phil\MentionsBundle\Application\MentionLimits` | From `limits` |
| `Phil\MentionsBundle\Application\Extraction\TextMentions` | On the pattern |
| `Phil\MentionsBundle\Application\MentionRecorder` | Public; on the store, the directory, `clock`, the limits and `event_dispatcher` |
| `Phil\MentionsBundle\Application\Mentions` | Public; the application's entry point |
| `Phil\MentionsBundle\Application\Rendering\MentionLinker` | Public; on the directory, the pattern and `limits.per_text` |
| `Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember` | Listener of `MemberMentioned`, when `phil/notifications-bundle` is enabled |
| `Phil\MentionsBundle\Application\Contract\MentionPreferences` | An alias of `preferences`, `SettingsMentionPreferences` or `AlwaysNotify` |
| `Phil\MentionsBundle\Infrastructure\Twig\MentionsExtension` | With Twig |
| `Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch` | With `phil/block-editor-bundle` |
| `Phil\MentionsBundle\Infrastructure\Comments\CommentMentions` | Public, with `phil/comments-bundle` |
| `Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory` | With `phil/comments-bundle`, for its `author_directory` |
| `Phil\MentionsBundle\Infrastructure\Comments\RecordCommentMentions` | With `phil/comments-bundle` and `comments.record`, a listener of its `CommentWritten` and `CommentDeleted` |

Every service keeps no state between requests: the bundle runs as it is under a worker that serves many requests.
