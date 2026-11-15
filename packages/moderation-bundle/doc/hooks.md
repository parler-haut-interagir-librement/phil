# Hooks

## The submission of a content

A package calls `Submissions::submit($target, $author, $text)` when a member creates a content — a question, a forum
post, a listing, a comment —, before it publishes it:

```php
$submission = $submissions->submit(TargetReference::of('forum.post', $post->id), $author, $post->body);

match ($submission->verdict) {
    ScreeningVerdict::Block => throw new PostRefused(),
    ScreeningVerdict::Hold => $post->holdForModeration(),
    ScreeningVerdict::Allow, ScreeningVerdict::Flag => $post->publish($submission->text),
};
```

| Verdict | What happened | The package |
|---|---|---|
| `Allow` | Nothing to note | Publishes |
| `Flag` | A watched word asked to flag: a report waits, the content is out | Publishes |
| `Hold` | A watched word asked to hold, or the first-post sandbox held it: a report waits | Keeps it unpublished until `Approve` |
| `Block` | A watched word refused it; nothing reaches the queue | Refuses it |

`text` is the text to keep, a word replaced or censored by the watched words; `null` when no text was given.

## The first-post sandbox

For the target types of `sandbox.types`, the first content of a member waits in the queue (source `sandbox`, reason
`first-post`). **Approving a held first post takes its author out of the sandbox**; their later content is published
at once. Removing it leaves them in. The policy is a port, `SandboxPolicy`: an application that holds by another rule —
a count of approved posts, the age of the account — replaces it.

## The watched words

`WordScreen` reads a text and answers a `Screening`: a verdict, the text to keep and the words that matched, which the
report keeps as its comment. The default, `NoWordScreen`, lets everything through; `phil/watched-words` brings the
adapter that blocks, censors, holds, flags, replaces, links and tags.

## Temporary hiding by trusted members

`TemporaryHides::hide($target, $comment)` lets a member of a high trust level hide a content at once: the target's
package hides it, and a report waits (source `trusted_hide`, reason `hidden-by-member`), where a moderator restores it
or confirms it. Who is trusted is a port, `TrustedHiders`; the default trusts nobody, and `phil/trust-levels` brings
the adapter. `mayHide()` says whether the current member may.

## Profiles

When the application gives a `MemberProfiles` service, the profiles become a target of type `profile`, its
identifier the member's: a profile is reported, held and decided on like any content. `Hide` hides the public profile
or resets its display name, avatar and description, `Restore` shows it again, `Remove` empties them — as the
application implements them.
