# Links

## The token

A token is 32 bytes of the secure generator, 256 bits, in URL-safe Base64 without padding: 43 characters of
`A–Z a–z 0–9 - _`. It names nothing. `LinkToken::parse()` accepts exactly that shape — 42 characters, then one whose
two last bits are zero, as the encoding of 32 bytes leaves them — and throws `InvalidToken` for anything else,
without repeating the text.

## The keyed hash

`TokenHasher` turns a token into what the store keeps: the HMAC-SHA-256 of the token under the application's key,
written `$hmac-sha256$v=1$` and 64 lowercase hexadecimal digits, 81 characters. A copy of the tables opens no link
without the key.

```php
use Phil\AccessLinks\TokenHasher;

$hasher = new TokenHasher($activeKey, $previousKey);
```

The keys are an ordered list: the first hashes new tokens, and every key finds a token. To rotate, put the new key
first and keep the old one after it; the links sent under the old key keep opening. Once every invitation linked
under a key has expired, drop that key. Each key is at least 32 bytes, eight keys at the most, none given twice. A
token is looked up by its hash under each key at once, by an index whose timing says nothing of a token since the
hash is keyed.

## The address

`LinkFormat` builds the link on `phil/url`: the page's address and one query parameter, `token` by default.

```php
use Phil\AccessLinks\LinkFormat;
use Phil\Url\Url;

$format = new LinkFormat(Url::fromString('https://events.example/rsvp?lang=fr'), 'token');
$format->for($token);   // https://events.example/rsvp?lang=fr&token=…
```

The parameter is 1 to 32 lowercase letters, digits and underscores; the page has no fragment; only a token is
carried. Serve the page with `Referrer-Policy: no-referrer`, so that a link out of it does not carry the token to
another site, and `Cache-Control: private, no-store`.

## Links issued again

```php
$again = $links->reissue($invitationId);
```

A recipient who lost their link gets another; the earlier links keep opening. Each reminder issues one too, since
the store cannot give a token back. An invitation has sixteen links at the most: the first, its reminders, and those
issued by hand; beyond, `reissue()` throws `InvalidConfiguration`.

## What the link is not

The link is not a login. It opens one invitation for whoever holds it, and the library does not make it a session.
An application that wants a signed login link for its members uses Symfony's login link, which signs the user's
properties and expires within minutes; an invitation link lasts until its expiry and is counted, revoked and
answered one invitation at a time.
