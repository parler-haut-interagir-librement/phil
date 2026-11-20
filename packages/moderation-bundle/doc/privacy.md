# Personal data

## Stable identifiers

A member is a `MemberId`: the stable identifier the application gives them, up to 190 visible characters. **An
identifier that looks like an e-mail address is refused**, and the user identifier of Symfony Security is never read
([Installation](installation.md#the-members)).

## The export and the erasure

With `phil/privacy-bundle`, `ModerationPrivacy` answers both contracts for the module `moderation`.

The export holds, for the member the request is about:

| Key | What |
|---|---|
| `reports` | The reports they made: target, reason, comment, moment, whether it is closed |
| `decisions_about_my_content` | The decisions on their content: action, role, public reason, moment |
| `decisions_taken` | The decisions they took as a moderator |
| `statements_addressed_to_me` | The statements of reasons addressed to them: target, action, reason, grounds, provision, facts, whether a report prompted it, whether automated means detected the content, moment |
| `notes_about_me` | The notes of the moderation on them, without their author |
| `sandbox_left_at` | When their first post was approved |

It never names another member: no moderator, no reporter, no author of a note; the private note of a decision stays out.

The erasure deletes the notes on the member, the statements of reasons addressed to them and the record of the sandbox,
and takes the member out of the reports they made, of the decisions — as author and as moderator, the role staying —, of
and of the notes they wrote. It may be replayed. The incidents a member started or lifted are answered by
`phil/incident-mode-bundle`.
