# Integrations

## The block editor

With `phil/block-editor-bundle`, the bundle registers `MemberReferenceSearch` and names it as the editor's
`mention_search`, unless the application names its own. The editor's `@` menu then suggests the directory's members:

| Suggestion | From |
|---|---|
| `reference` | `Member::$reference`, `user:42` |
| `label` | `Member::$name` |
| `url` | `Member::$url` |
| `hint` | `@` and `Member::$handle` |

A leading `@` of the query is dropped. A member whose reference the editor cannot write — a kind over 32 characters,
an identifier with other characters than letters, digits, `_`, `.`, `/` and `-` — is passed over. The search answers
at the editor's endpoint, `phil_block_editor_mentions`, which the `mention` controller reads too: import the editor's
routes behind the firewall.

## Comments

With `phil/comments-bundle` installed beside it, the bundle registers `RecordCommentMentions`, a listener of that
bundle's `CommentWritten` and `CommentDeleted`: each comment written or edited — on a passage or on the whole page —
has its mentions recorded, and the members it mentions for the first time are notified through
`phil/notifications-bundle`; each comment deleted has its mentions forgotten. Nothing is registered when either bundle
is missing, and nothing in `phil/comments-bundle` depends on this one.

Each comment is the subject `comment:<id>`, its author the comment's, its context the resource of its thread, so that
only the members who may see that resource are notified. The comment is saved before it is read: a comment that
mentions more members than `limits.per_text` stays as written and notifies nobody.

`comments.record: false` turns the listener off, for an application that records the mentions itself with
`CommentMentions`:

```php
$discussion = $comments->reply($thread, $author, $body);
$commentMentions->discussion($discussion);   // every comment; only new mentions notify

$commentMentions->comment($comment, $thread);
$commentMentions->forget($comment->id);       // after deleting it
```

`comments.markdown: true` reads the comments as Markdown. `MemberAuthorDirectory` names the comments' authors from
the directory:

```yaml
phil_comments:
    author_directory: Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory
```

## Settings

With `phil/settings-bundle`, the bundle declares the setting `notifications.mentions` — a boolean, on by default, in
the notifications section, after `notifications.web_push` — and reads it before notifying. Its label is
`notifications.mentions.label` in the `phil_mentions` domain, shipped in English and French.

## Markdown

`phil/markup-bundle` renders Markdown; `mentions` comes after it, on the HTML: `{{ body|markdown|mentions }}`. When
recording, `inText(..., markdown: true)` leaves out the same code and addresses the rendering shows as code and links.
