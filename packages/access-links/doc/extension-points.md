# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library is plain PHP and declares no service. `AccessLinks` takes a store, a hasher, a link format, a clock, and
optionally a reminder schedule and a randomizer.

## `InvitationStore`

`Phil\AccessLinks\Contract\InvitationStore` — where the invitations and the hashes of their links live.
`InMemoryInvitationStore` and `Dbal\DbalInvitationStore` are shipped; see [Storage](storage.md).

| Method | Contract |
|---|---|
| `add(Invitation $invitation, string $tokenHash): bool` | Adds the invitation and its first link; `false`, adding nothing, for a recipient invited to the campaign before |
| `addLink(string $invitationId, string $tokenHash, int $maxLinks): bool` | Adds a link unless the invitation has `$maxLinks` or is unknown |
| `get(string $invitationId): ?Invitation` | The invitation, or `null` |
| `findByToken(array $candidates): ?Invitation` | The invitation of the link one of these hashes stands for |
| `respond(string $invitationId, string $answer, DateTimeImmutable $now): bool` | Records the answer atomically, only while not revoked, valid and not closed; sets the first and last answer, stops the reminders |
| `claimReminder(string $invitationId, int $sent, ?DateTimeImmutable $next, DateTimeImmutable $now): bool` | Claims the due reminder atomically when `$sent` were sent and there is no answer, revocation or closure |
| `due(DateTimeImmutable $now, int $limit): array` | The identifiers of the invitations whose reminder is due, the most overdue first |
| `revoke(string $invitationId, DateTimeImmutable $now): bool` | Revokes; `false` when revoked before or unknown |
| `counts(string $campaign): CampaignCounts` | Invited, responded, revoked before answering |

A store of the application's own receives hashes, never tokens, and keeps every write conditional: of two calls at
the same moment, one wins.

## `ReminderSender`

`Phil\AccessLinks\Contract\ReminderSender` — how the application reaches a recipient; see [Reminders](reminders.md).

| Method | Contract |
|---|---|
| `sendReminder(IssuedLink $link): void` | Finds how to reach `$link->invitation->recipient` and sends `$link->url` |

## The other arguments

| Argument | What to pass |
|---|---|
| `TokenHasher` | The ordered keys, read from the secrets vault |
| `LinkFormat` | The invitation page's address and the parameter's name |
| `Psr\Clock\ClockInterface` | The application's clock; a `MockClock` in tests |
| `ReminderSchedule` | The delays of the reminders; none by default |
| `Random\Randomizer` | The `Secure` engine by default; a seeded engine in tests only |
