# Handles

`Handle::of($service, $input)` reads a handle and keeps it canonical; it throws `InvalidHandle` for an input the
service does not read.

```php
$handle = Handle::of(KnownMessenger::WhatsApp, '+1 (514) 555-0123');
$handle->value;     // "+15145550123"
$handle->link();    // "https://wa.me/15145550123"
$handle->equals(Handle::of(KnownMessenger::WhatsApp, 'wa.me/15145550123'));   // true
```

## A handle kept

A handle is kept as its service and its canonical value, `$handle->value`. `Handle::fromCanonical($service, $value)`
takes it back as it is, without reading it again: it was read when it was kept. Reading it again with `of()` would
refuse a phone number that a later import of the numbering plans of `phil/phone` no longer knows, and the entity that
holds it could not be loaded. A text a person typed goes through `of()`.

```php
Handle::fromCanonical(KnownMessenger::WhatsApp, $row['handle']);
```

## Detecting the service

```php
Messengers::detect('https://t.me/adalove', EnumSet::all(KnownMessenger::class));   // the Telegram handle, or null
Messengers::handle('@ada:matrix.org', $accepted);                                  // or InvalidHandle
```

The first service, set by set and in each enum's declaration order, that reads the input wins; the input is read
once, and the handle is the canonical form that service gave. **Handles collide**: a
user name may be read by Telegram, Line and Discord alike, and a phone number by WhatsApp, Signal, Viber and iMessage.
Detection is right for links, which name their service, and for distinctive forms — a Matrix ID, an XMPP address —;
elsewhere, ask the person for the service, as `phil/instant-messenger-bundle`'s field does.
