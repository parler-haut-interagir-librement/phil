# Invitations

## Inviting

```php
$issued = $links->invite(
    'dinner-2026',                                // the campaign, as the application names it
    'member-1842',                                // the recipient's stable identifier
    new DateTimeImmutable('2026-12-01 12:00'),    // the closure: the answer stops changing
    new DateTimeImmutable('2026-12-15 12:00'),    // the expiry: the link stops opening, the closure by default
    new DateTimeImmutable('2026-11-20 09:00'),    // the opening: the link starts opening, now by default
);

$issued->invitation;   // Invitation
$issued->url;          // the link to send, a phil/url Url
$issued->token;        // the token alone, for a template that builds its own address
```

| Value | Bounds |
|---|---|
| Campaign | 1 to 100 letters, digits, colons, dots, hyphens and underscores: `poll:42`, `dinner-2026` |
| Recipient | 1 to 191 bytes, never holding `@`: an identifier of the application, never an email address |
| Moments | Closure after now and after the opening; expiry at the closure or after |

A recipient is invited once per campaign: a second invitation throws `AlreadyInvited`, and a lost link is issued
again with `reissue()`; see [Links](links.md). The `IssuedLink` refuses to be serialized and hides its token and
address from `var_dump()`: send it, and let it go.

## The moments

| Moment | The link | The answer |
|---|---|---|
| Before `validFrom` | Opens nothing | Cannot be given |
| From `validFrom` until `closesAt` | Opens, editable | Given and changed |
| From `closesAt` until `expiresAt` | Opens, read-only | Shown, no longer changed |
| From `expiresAt` | Opens nothing | — |

Every moment comes from the clock and is compared by order. `Invitation::opensAt($now)` and `isEditableAt($now)` say
the same for an invitation the application holds.

## Opening

```php
$access = $links->open($request->query->getString('token'));

if (null === $access) {
    // malformed, unknown, revoked, not yet valid or expired: one page for all
}

$access->invitation->answer;   // the answer so far, or null
$access->editable;             // whether the form may be shown
```

Opening changes nothing. A mail client that opens the link to preview it, or a security scanner that follows it,
spends nothing and answers nothing: only `respond()` writes.

## Answering

```php
$invitation = $links->respond($token, 'yes, with one guest');
$invitation->respondedAt;   // the first answer
$invitation->answeredAt;    // the last change
```

The answer is 1 to 4096 bytes of UTF-8, as the application encodes it: a word, a choice, a small JSON document.
Answering again changes it until the closure. A link that opens nothing throws `LinkNotUsable`, whose message does not
say why; an answer after the closure throws `InvitationClosed`. An answer stops the reminders.

## Revoking and counting

```php
$links->revoke($invitationId);    // false when revoked before; UnknownInvitation when unknown
$links->invitation($invitationId);
$links->counts('dinner-2026');    // CampaignCounts: invited, responded, revoked, pending()
```

A revoked invitation's links open nothing. An invitation revoked after it was answered counts as answered.
