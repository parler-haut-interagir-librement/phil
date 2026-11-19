# Storing and reading secrets

`UserSecrets` is the bundle's service. Every method names the owner — the person, by the opaque identifier the
application knows them by — and the secret's name, unique among the owner's secrets.

## Storing

```php
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

$owner = Owner::fromString($user->getUserIdentifier());

$secrets->store($owner, SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of($key));
$secrets->store($owner, SecretName::fromString('github'), ServiceName::fromString('github'), OAuthToken::of(
    $response['access_token'],
    $response['refresh_token'] ?? null,
    new DateTimeImmutable('+'.$response['expires_in'].' seconds'),
    explode(' ', $response['scope']),
));
```

Storing under a name that exists replaces the value, its service and its kind, and lifts a revocation; the creation
date stays. The name is the owner's choice — `github.work` and `github.home` may both open `github`.

An owner is 1 to 255 bytes without whitespace or control characters; a name and a service are lowercase letters,
digits, `_`, `.` and `-`, starting with a letter, at most 64 characters.

## Listing

```php
foreach ($secrets->secrets($owner) as $summary) {
    // $summary->name, ->service, ->kind, ->createdAt, ->updatedAt, ->lastUsedAt, ->expiresAt, ->revokedAt
}
```

A `SecretSummary` has everything but the value; revoked secrets are listed too.

## Reading by a task of the owner

Nothing reads a secret but a `SecretAccess`, a task run for the owner, which names its purpose:

```php
$access = SecretAccess::forTaskOf($owner, 'nightly calendar sync');

$credential = $secrets->reveal($access, SecretName::fromString('openai'));   // Credential
$key = $secrets->apiKey($access, SecretName::fromString('openai'));          // ApiKey
$token = $secrets->accessToken($access, SecretName::fromString('github'));   // OAuthToken, refreshed if needed
```

The access reads its owner's secrets and no one else's: the lookup is by its owner, so another person's secret is
simply not found. The application builds the access where it already knows whose task it runs — the request of the
signed-in person, a message handler whose message names the person — and never from a value the request supplies.
The purpose, 1 to 255 bytes, goes to the audit trail with the read.

A read dates the secret's last use. The values come as `SecretValue`, which prints as `[redacted]` in `var_dump()`,
`print_r()` and stack traces and refuses serialization; call `reveal()` at the moment the value is sent to the service.

## Revoking, removing, erasing

```php
$secrets->revoke($owner, SecretName::fromString('openai'));   // value wiped, row kept with its date
$secrets->remove($owner, SecretName::fromString('openai'));   // row gone; false when there was none
$secrets->eraseOwner($owner);                                 // every secret of the owner gone; how many
```

A revoked secret cannot be read until the owner stores a new value under its name. `eraseOwner()` is what the privacy
module runs when the account is erased, as [audit and privacy](audit-and-privacy.md) shows; it can run again without
harm.

## Revocation at the service

When an OAuth token is revoked, removed or erased, and a `TokenRevoker` of the application serves its service, the
bundle asks the service to revoke it too, once the token is gone locally: the first revoker that supports the service
receives the token, usually for the service's revocation endpoint ([RFC 7009](https://www.rfc-editor.org/rfc/rfc7009)).
A `revoked_at_service` event follows. A revoker that throws — the service is down, refuses, or the revoker has a bug —
never brings the token back and never stops an erasure: the failure is written to the `logger` service at `warning`
level, with its exception, and a `service_revocation_failed` event goes to the auditors. A sealed value that no longer
opens is logged the same way and wiped all the same.

API keys are not revoked at the service: few services offer an endpoint for it, and the owner revokes a key where they
made it. A secret whose service no revoker serves is not opened when it goes. [OAuth tokens](oauth-tokens.md#writing-a-revoker)
shows a revoker.

## Screens for the owner

The bundle ships no form, controller or template. The screens where an owner pastes a key, connects a service by
OAuth, lists their secrets and revokes or removes one belong to the application, which knows its users, its routes,
its look and how each service is connected — an OAuth connection is a redirect flow of the application's own client.
They are a few calls each: `store()` from the form or the OAuth callback, `secrets()` for the list, `revoke()` and
`remove()` behind buttons protected against CSRF. Show the summary of each secret — its service, its dates, whether it
was revoked — and never its value: the bundle hands values only to a `SecretAccess`.
