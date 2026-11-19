# Reading a message

## The reader

`MimeReader::read()` takes the raw message as a string; `readStream()` takes an open stream and reads from its current
position, at most the octets of the limits. Both return the message as a `Part`, the root of its tree.

```php
$reader = new MimeReader(new Limits(maxBytes: 10_485_760));
$message = $reader->read($raw);
$message = $reader->readStream(fopen('php://stdin', 'rb'));
```

The header lines end at the first empty line, written with a line feed or a carriage return and a line feed. A part
without an empty line is a part of header lines only; a part that starts with an empty line has no header fields.

## Parts

A `Part` holds:

| Property | What it holds |
|---|---|
| `headers` | Its header fields, as `Headers` |
| `type` | Its media type in lowercase; `text/plain` when none is valid, `message/rfc822` in a digest |
| `parameters` | The parameters of its type, by name in lowercase, decoded |
| `encoding` | Its transfer encoding in lowercase, empty when none is given |
| `parts` | Its child parts, when it is a multipart |
| `message` | The message it holds, when it is a `message/rfc822` or `message/global` |
| `incomplete` | Whether a limit cut it |

A multipart of any subtype is split at its boundary. A delimiter counts at the start of a line, followed by nothing but
spaces; the line break before it belongs to it; the preamble and the epilogue are not parts, and a multipart whose
closing line is missing ends with its text. A container that is encoded, though it should not be, is decoded before
its parts are read.

`walk()` gives the part and every part under it, depth first; `walk(true)` enters the messages it holds too.
`find($type)` gives the first part of a type, without entering held messages.

## Header fields

`Headers` keeps the fields in their order, unfolded and trimmed, their names compared without case.

| Method | Gives |
|---|---|
| `get($name)` | The value of the first field, as written |
| `all($name)` | The values of every field of the name |
| `has($name)` | Whether a field of the name is present |
| `decoded($name)` | The value with its encoded words (RFC 2047) decoded, always valid UTF-8 |
| `parameterized($name)` | A `HeaderValue`: the value before the first semicolon and its parameters |
| `mailboxes($name)` | The `Mailbox` list of every field of the name |

Encoded words are decoded in B and Q, the space between two of them dropped, and the octets of adjacent words in one
character set joined before conversion, so that a character split between two words is read whole. Raw 8-bit text in a
field is kept, its invalid UTF-8 replaced.

`HeaderValue::parse()` joins the sections of a parameter in their order (`filename*0`, `filename*1`), percent-decodes
the extended ones and converts them from the character set of the first (`filename*=UTF-8''r%C3%A9sum%C3%A9.pdf`). A
parameter written as encoded words, as some mailers do for a file name, is decoded too.

`mailboxes()` reads address lists: display names quoted or not, comments skipped, groups flattened. A `Mailbox` holds
the address as written and its decoded display name; `domain()` gives its domain in lowercase. The address is not
validated: check it with the application's own rules before sending anything to it.

## Bodies

| Method | Gives |
|---|---|
| `content()` | The body with its transfer encoding undone, as octets |
| `text()` | The body converted from its `charset` to UTF-8 |
| `writeTo($stream)` | Writes the decoded body to an open stream, chunk by chunk; returns the octets written |
| `stream()` | The decoded body in a temporary stream, rewound, in memory up to 2 MiB and on disk beyond |

Base64 drops what is not of its alphabet and decodes a last incomplete group; quoted-printable keeps a malformed escape
as it is written; any other encoding gives the octets as they are. The parts of one message share the octets read: a
part holds a range of them, and decodes it only when asked.

`text()` reads a missing, `us-ascii` or unknown character set as UTF-8 with its invalid sequences replaced, and
ISO-8859-1 as Windows-1252, its superset, as browsers do. A set mbstring does not know, such as TIS-620 or Macintosh,
is converted by iconv, which drops the octets the set does not define. A name that is a transfer encoding or an entity
scheme rather than a character set is not used for conversion.

## Text, HTML and attachments

`textBody()` and `htmlBody()` give the text of the first `text/plain` or `text/html` part that is not an attachment,
outside held messages. The HTML is returned as text: nothing in it is run, fetched or cleaned, which is the work of an
HTML sanitizer before it is shown.

A part is an attachment when it is disposed so, or when it is named and not disposed inline; an inline image of a
`multipart/related` is not one. `attachments()` lists them, outside held messages. `filename()` gives the name from the
disposition or else the type, decoded, without any directory, control character or leading dot, cut to 255 octets.

## Reports

Delivery status notifications (RFC 3464), disposition notifications (RFC 8098), feedback reports (RFC 5965) and
`text/rfc822-headers` parts write groups of fields separated by empty lines. `fieldGroups()` reads them as a list of
`Headers`, empty groups skipped:

```php
$report = $message->find('message/delivery-status');
[$perMessage, $firstRecipient] = $report->fieldGroups();
$firstRecipient->get('Final-Recipient'); // 'rfc822; gone@example.com'
$firstRecipient->get('Status');          // '5.1.1'

$returned = $message->find('text/rfc822-headers')?->fieldGroups()[0];
$returned?->get('Message-ID');
```

A returned message sent whole is a `message/rfc822` part, read as the message it holds: `$part->message->headers`.
