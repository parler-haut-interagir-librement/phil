# Invitations

An invitation lets whoever holds its token join an organization with the roles it carries: until it expires, as many
times as it allows, unless it is revoked.

## Creating one

```php
$issued = $invitations->create($acme->id, $actor, ['member'], expiresAt: $clock->now()->modify('+3 days'), maxUses: 10);

$issued->token;          // 43 characters, shown once: put it in a link and send it
$issued->invitation;     // its identifier, roles, uses, moments and creator
```

The actor holds `members.invite` and grants only roles whose permissions they hold. Without an expiry, the invitation
lasts `invitations.default_lifetime` days, and never more than `max_lifetime`; without a number of uses, it allows
`default_uses`, and never more than `max_uses`.

The token is 32 bytes of PHP's secure generator in URL-safe Base64, the tokens of `phil/access-links`. The table keeps
only its HMAC-SHA-256 under the application's key: a copy of the table opens nothing. The token goes to its creator
once and to no event, log or table.

## Accepting one

```php
$invitation = $invitations->preview($token);                         // what it grants, without using it
$membership = $invitations->accept($token, MemberId::fromString($user->getId()));
```

`accept()` uses the invitation once and adds the member with its roles. Each use is **one conditional `UPDATE`** —
neither revoked nor used up nor expired at that moment — whose row count is checked: of two members taking its last use
at once, one joins and the other is refused.

An invitation is refused with `InvitationNotUsable`, whose message is the same whatever the cause, so that it tells
nothing of a token; its `reason` tells the application which:

| `reason` | When |
|---|---|
| `unknown` | The token is malformed, or no invitation has it |
| `expired` | Its expiry has passed |
| `revoked` | It was revoked |
| `used_up` | It was used as many times as it allows |
| `archived` | Its organization is archived |
| `creator` | Its creator is no longer a member who may invite and grant its roles, or was erased |

A member who is already a member gets `AlreadyMember`, and the invitation is not used.

## Revoking and listing

```php
$invitations->revoke($acme->id, $invitation->id, $actor);    // members.invite
$invitations->invitations($acme->id);                         // the newest first
```

## Purging

`phil:organizations:purge-invitations` deletes the invitations that expired or were revoked more than
`purge_retention` days ago; `Invitations::purge()` does the same with a retention in seconds. Run it each day.

## Rotating the key

`hash_key` takes a list: the first key hashes new tokens, and the others still find the invitations made under them.
Add a new key first, then drop the old one once its invitations have expired:

```yaml
phil_organizations:
    invitations:
        hash_key: ['%env(ORGANIZATIONS_INVITATION_KEY)%', '%env(ORGANIZATIONS_INVITATION_KEY_PREVIOUS)%']
```

## Joining by a verified domain

An organization may record the email domain it verified; verifying it — a DNS record, a message to an address of the
domain — is the application's, before the call:

```php
$organizations->useDomain($acme->id, $actor, 'acme.org');    // organization.manage; null removes it
```

With `domain_join.enabled`, off by default, a member whose address the application has verified joins an organization
whose verified domain is the address's, with the roles of `domain_join.roles`, `member` by default and never `owner`:

```php
$join->organizationsFor($verifiedAddress);                   // the active organizations of that domain
$join->join($acme->id, $member, $verifiedAddress);
```

The domain compares exactly, in lowercase ASCII — an internationalized domain in its `xn--` form —: `sub.acme.org` is
not `acme.org`. The address is read for its domain and never kept, logged or dispatched.
