# phil/mentions-bundle

**@mentions of members in a text, and their notification, for Symfony applications.**

phil/mentions-bundle lets a writer name another member in what they write — `@ada` in a comment, a proposal, a page —
and lets that member know. When the application saves a text, the bundle finds its mentions: handles typed after `@`
in a plain text or in Markdown, with a pattern the application chooses, or the mention marks a UJO (Universal JSON
Output) document holds, `<a data-mention="user:42">@Ada</a>`. The application's member directory resolves them; the
author, and the members who may not see the content, are left out; the others are stored for that text, and only the
members it mentions for the first time are announced and notified. Saving the same text again notifies nobody; an edit
notifies only the members it adds. A Twig filter turns the handles of a text into links to the members' profiles, and a
Stimulus controller offers the members as the writer types `@` in a plain text field.

It is for any Symfony application where people write to each other: comments on a draft, proposals in a shared
project, posts in a community, messages of a team. The application names its members as references of
`phil/resource` (`user:42`), and writes one service, the directory, that finds them by handle or reference, searches
them, and says whether one may see a piece of content. The notification goes through `phil/notifications-bundle`, as
the type `member.mentioned`, on the channels each member chose.

It is a Symfony bundle on `phil/resource`, `phil/ujo`, `phil/notifications-bundle` and Doctrine ORM: mentions live in
one table, or in memory for tests. With `phil/block-editor-bundle`, the editor's `@` menu searches the directory; with
`phil/comments-bundle`, comments are recorded and their authors named from it; with `phil/settings-bundle`, each member
chooses whether to be notified. The mentions of social networks and forges serve as models of behaviour.

## Status

| Part | State |
|---|---|
| `Mention`, `MentionPattern`: a handle or a mark, what a handle looks like | Implemented and tested |
| `TextMentions`, `UjoMentions`: the mentions of a plain text, of Markdown, of a UJO document | Implemented, tested and fuzzed |
| `MentionRecorder`, `Mentions`: mentions stored per subject, the diff of an edit, `MemberMentioned` | Implemented and tested |
| `MentionLimits`: the members per text and the length of a text | Implemented and tested |
| Doctrine and in-memory stores | Implemented and tested, on SQLite, MySQL 8 and PostgreSQL 17; rows added by an upsert, the store in memory emptied between requests |
| `NotifyMentionedMember`, the `member.mentioned` type on the centre, email and Web Push, `MentionPreferences` | Implemented and tested |
| `MentionLinker` and the `mentions` Twig filter | Implemented and tested |
| `MemberReferenceSearch`, `CommentMentions`, `RecordCommentMentions`, `MemberAuthorDirectory`: the integrations | Implemented and tested |
| The `mention` controller: the `@` autocomplete of plain text fields | Implemented; pure modules tested under Node, the controller driven in Chrome by the browser suite |
| The Flex recipe: the bundle, its configuration and a directory to fill | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the table, the directory, the other bundles. |
| [Configuration](doc/configuration.md) | Every option, and the services registered. |
| [Mentions](doc/mentions.md) | Handles, the pattern, Markdown, UJO marks, the limits. |
| [Recording](doc/recording.md) | Subjects, contexts, visibility, edits, the event and the stores. |
| [Notifications](doc/notifications.md) | The notification type, its translations, the members' preference. |
| [Rendering](doc/rendering.md) | The `mentions` filter and `MentionLinker`. |
| [Integrations](doc/integrations.md) | The block editor, the comments and the settings. |
| [JavaScript](doc/javascript.md) | The controller, its values, what it does, its modules and the stylesheet. |
| [Exceptions](doc/exceptions.md) | The bundle's exceptions. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the benchmarks, the fuzz target and the checks in a browser. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/mentions-bundle
```

```yaml
# config/packages/phil_mentions.yaml
phil_mentions:
    member_directory: App\Members\MemberDirectory   # a MemberDirectory
```

```php
$mentions->inText(ResourceReference::of('comment', $comment->id), $author, $comment->body, markdown: true, context: $page);
```

```twig
{{ comment.body|mentions }}
<textarea name="body" data-controller="phil--mentions-bundle--mention"
          data-phil--mentions-bundle--mention-url-value="{{ path('phil_block_editor_mentions') }}"></textarea>
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine ORM 3, `phil/resource`, `phil/ujo` and `phil/notifications-bundle`. For the
filter, Twig 3; for the controller, Stimulus 3 and the routes of `phil/block-editor-bundle`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
