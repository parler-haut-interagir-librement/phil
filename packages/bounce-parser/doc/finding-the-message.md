# Finding the message

A report is about a message the application sent; the application finds it to know which send, which campaign and
which recipient the report speaks of.

## The message returned

`BounceReport::$original` is an `OriginalMessage` built from the first part that holds a message — `message/rfc822`,
`message/global` — or its header fields — `text/rfc822-headers`, `message/global-headers`. For an automatic reply that
returns nothing, it is the `In-Reply-To` of the reply, as a `Message-ID`.

| Method | Gives |
|---|---|
| `messageId()` | The `Message-ID` without its angle brackets |
| `feedbackId()` | The `Feedback-ID` some mailbox providers aggregate complaints by |
| `header(string)` | Any field: a field of the application's own, such as an identifier of the send |
| `headers` | Every field, as `Mime\Headers` |

Servers often return only the header fields, sometimes nothing: a field of the application's own in the message, or
its `Message-ID`, is the surest way back — as long as it is an identifier, not personal data.

## VERP

A variable envelope return path gives each message its own return path, so that the address its bounce reaches names
it, whatever the bounce says or leaves out.

```php
$verp = new Verp('bounces', 'mail.example.net');

$verp->encodeSigned('m-42', $key);          // bounces+m-42.<32 hex digits>@mail.example.net
$verp->encode('m-42');                      // bounces+m-42@mail.example.net
$verp->encodeRecipient('ada@example.org');  // bounces+ada=example.org@mail.example.net

$verp->decodeSigned($report->verpTag, $key);                       // 'm-42', or null when the tag was not signed
$verp->decode('<bounces+m-42@mail.example.net>');                // 'm-42'
$verp->decodeRecipient('bounces+ada=example.org@mail.example.net'); // 'ada@example.org'
```

The tag holds only the characters of an atom, without two dots in a row. The separator is one of `+ - = _ .` — `+`
by default — and the mail server delivers every `bounces+…` address to the bounce mailbox. Given a `Verp`, the parser
reads the tag from the `Delivered-To`, `X-Original-To` and `Envelope-To` fields of the bounce, in that order, into
`BounceReport::$verpTag`. Those are the fields the receiving mail server adds with the envelope recipient; `To` is not
read, since the sender writes it. A server that adds another field, or none of these, is given its own list:

```php
$parser = new BounceParser(verp: $verp, envelopeFields: ['X-Envelope-To']);
```

Only list a field the application's own mail server writes: a field it lets through from the message is the sender's.

Anyone can mail a return path. A tag the application acts on — a hard bounce counted, a recipient suppressed — must be
one only it can write: `encodeSigned()` appends to the identifier an HMAC-SHA256 of the identifier and of the mailbox
under the application's key, at least 16 bytes, cut to 128 bits; `decodeSigned()` gives back the identifier only when
the signature matches, compared in constant time. A classic VERP tag names the recipient in clear, to every server on
the way, and anyone can write one: `encodeRecipient()` is enough to sort the mailbox, not to decide a suppression.
