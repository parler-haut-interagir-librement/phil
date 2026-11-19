# phil/mime-reader

**Internet messages read with their MIME structure, safely, whatever the sender wrote.**

phil/mime-reader reads a raw email — as a bounce mailbox, an inbound address or an archive receives it — into a tree
of parts: its header fields, folded and encoded words decoded to UTF-8, its media types and their parameters, sections
and character sets of RFC 2231 included, its multiparts of every subtype (`mixed`, `alternative`, `related`, `report`,
`digest`), the messages it holds (`message/rfc822`, `message/global`), and the groups of fields of delivery reports and
feedback reports.

Bodies stay encoded until a part is asked for them: `content()` undoes base64 or quoted-printable, `text()` converts
the declared character set to UTF-8, and `writeTo()` sends an attachment to a stream chunk by chunk, never holding it
decoded whole. File names are proposed without any directory or control character.

The reader is made for hostile input. It never throws on a message: what it cannot read is kept as text. The octets
read, the nesting, the number of parts, the header fields of a part and the parameters of a field are bounded by
`Limits`, the part a limit cuts is marked incomplete, and every search stays inside its part, so that the time spent
grows with the octets of the message. Nothing in a message is run, fetched or unserialized.

It is a plain PHP library with no dependency beyond `ext-iconv`, `ext-mbstring` and `ext-sodium`.

## Status

| Part | State |
|---|---|
| Header fields: unfolding, encoded words (RFC 2047), address lists | Implemented and tested |
| Parameters, with sections and character sets (RFC 2231) | Implemented and tested |
| Multiparts of every subtype, nested; held messages; digests | Implemented, tested and fuzzed |
| Base64 and quoted-printable, decoded on demand and in chunks | Implemented and tested |
| Character sets converted to UTF-8 | Implemented and tested |
| Field groups of reports and of `text/rfc822-headers` | Implemented and tested |
| Text and HTML bodies, attachments, safe file names, streams | Implemented and tested |
| Limits on octets, depth, parts, fields and parameters | Implemented, tested and benchmarked |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reading a message](doc/reading.md) | The reader, parts, header fields, bodies, attachments, reports. |
| [Hostile input](doc/hostile-input.md) | The limits, what the reader refuses, what it never does. |
| [Symfony Mime](doc/symfony-mime.md) | What `symfony/mime` offers, and why the reader does not build on it. |
| [Extension points](doc/extension-points.md) | What an application configures or relies on. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/mime-reader
```

```php
use Phil\MimeReader\MimeReader;

$message = new MimeReader()->read($raw);

$message->headers->decoded('Subject');      // 'Rapport de la semaine'
$message->headers->mailboxes('From');       // [Mailbox('help@example.org', 'Support')]
$message->textBody();                       // the text, in UTF-8

foreach ($message->attachments() as $attachment) {
    $attachment->filename();                // 'résumé.pdf', never a path
    $attachment->writeTo($stream);          // decoded chunk by chunk
}

$status = $message->find('message/delivery-status');
$status?->fieldGroups()[1]->get('Status'); // '5.1.1'
```

## Requirements

PHP 8.5 or later with `ext-iconv`, `ext-mbstring` and `ext-sodium`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
