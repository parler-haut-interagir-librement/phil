# phil/bounce-parser

**Know what every message on your bounce mailbox says, and what to do about the address it names.**

phil/bounce-parser reads a message received on a bounce mailbox and says what it is: a delivery status notification
(RFC 3464, and RFC 6533 for international addresses), an abuse feedback report (RFC 5965), a bounce written as text by
a server that sends no notification, or an automatic reply (RFC 3834). From it, it takes each recipient, the enhanced
status code (RFC 3463), the kind of bounce — hard, soft, complaint, automatic reply, success —, the action for the
application, and the message it is about, by its `Message-ID`, its `Feedback-ID`, a field of the application's own, or
the tag of a VERP return path. Rules the application declares, in their order, decide what no standard describes.

It is for any PHP application that sends mail through its own SMTP server and reads the bounces from a mailbox: a
newsletter, the notifications of a site, a mailing list. It turns the bounce mailbox into counters of hard and soft
bounces, unsubscriptions on complaints, and a short queue of messages for a person to read.

It is a plain PHP library whose requirements are `ext-ctype`, `ext-iconv`, `ext-mbstring` and
`ext-sodium`. Its MIME reader never throws, whatever the message holds, and bounds its work: octets read, depth,
parts, header fields and text. An abuse report unsubscribes only when an authenticator vouches for it.

The design: `Mime\MimeParser` reads the message into `Mime\Part`s; `BounceParser` recognises the report and returns a
`BounceReport` — `ReportFormat`, `BounceKind`, `BounceAction`, the `Recipient`s with their `StatusCode`, the
`FeedbackReport`, the `OriginalMessage` —, then tries the `Rule\Rules`; `Verp` writes and reads return paths;
`Contract\ReportAuthenticator` vouches for abuse reports.

## Status

| Part | State |
|---|---|
| MIME: header fields, encoded words, media types, base64, quoted-printable, multiparts, held messages, digests | Implemented and tested |
| Delivery status notifications (RFC 3464, RFC 6533): every recipient, action, status, diagnostic, SMTP code | Implemented and tested |
| Enhanced status codes (RFC 3463): parsing, finding in text, failures about the message rather than the mailbox | Implemented and tested |
| Abuse feedback reports (RFC 5965, RFC 6591): every field, complaint or not | Implemented and tested |
| Authentication of reports: `Authentication-Results` of the application's server, or trust every report | Implemented and tested |
| Bounces written as text: sender, subject, first address, first status or SMTP code | Implemented and tested |
| Automatic replies: `Auto-Submitted`, `X-Autoreply`, `X-Autorespond`, `Precedence: auto_reply` | Implemented and tested |
| The message a report is about: `Message-ID`, `Feedback-ID`, any field, `In-Reply-To` of a reply | Implemented and tested |
| VERP return paths: signed tags, tags and classic recipient encoding | Implemented and tested |
| Rules on the subject, the sender, the text, the diagnostics or a field, ordered by name, each with its action | Implemented and tested |
| Proposed rules for unrecognised messages | Not started: a bundle's work, with its review queue |
| Reading a mailbox (IMAP, POP3, mbox) | Not started: a bundle's work; the library takes one message as text |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the parser as a service. |
| [Parsing](doc/parsing.md) | What the parser recognises, in which order, and the report it returns. |
| [Kinds and actions](doc/kinds-and-actions.md) | How a bounce becomes hard or soft, and the action of each kind. |
| [Status codes](doc/status-codes.md) | `StatusCode`, its class, subject and detail, and the failures about the message. |
| [Feedback reports](doc/feedback-reports.md) | Abuse reports, their fields, and the authenticators. |
| [Finding the message](doc/finding-the-message.md) | `OriginalMessage`, `Feedback-ID`, the application's own field, and VERP. |
| [Rules](doc/rules.md) | Declaring rules, their fields and scopes, ordering them, and their actions. |
| [MIME](doc/mime.md) | The MIME reader used on its own, and its limits. |
| [Threats](doc/threats.md) | What a hostile message can attempt, and what the library and the application do about it. |
| [Extension points](doc/extension-points.md) | The port, the arguments and the rules an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests, fuzzing and mutation bar, and how to test code that reads bounces. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/bounce-parser
```

```php
use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Verp;

$verp = new Verp('bounces', 'mail.example.net');
// Each message is sent with its own return path: $verp->encode($messageId).

$parser = new BounceParser(
    rules: new Rules(Rule::containing('quota', Field::Body, 'over quota', BounceKind::Soft)),
    verp: $verp,
    authenticator: new AuthenticationResults(['mail.example.net'], ['fbl.example.com']),
);

$report = $parser->parse($rawMessage);

$report->kind;            // BounceKind::Hard
$report->action;          // BounceAction::CountHard
$report->addresses();     // ['ada@example.org']
$report->verpTag;         // the identifier the message was sent with
$report->recipients[0]->status?->toString(); // '5.1.1'
```

## Requirements

PHP 8.5 or later, `ext-ctype`, `ext-iconv`, `ext-mbstring` and `ext-sodium`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
