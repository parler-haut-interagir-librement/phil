# Parsing

`BounceParser::parse(string $raw): BounceReport` takes one message as received, header fields and body, and never
throws. It reads the message with `Mime\MimeParser`, then tries each reading in order; the first that recognises the
message decides its `ReportFormat`.

| Order | Format | Recognised by |
|---|---|---|
| 1 | `DeliveryStatus` | A `message/delivery-status` or `message/global-delivery-status` part |
| 2 | `FeedbackReport` | A `message/feedback-report` part |
| 3 | `PlainText` | A sender `MAILER-DAEMON`, `mail-daemon` or `postmaster`, the null return path `<>`, or a subject such servers write |
| 4 | `AutoReply` | `Auto-Submitted` other than `no`, `X-Autoreply`, `X-Autorespond`, `Precedence: auto_reply` |
| 5 | `Unrecognised` | None of these |

The parts of a message the report holds are not searched for a report: a notification forwarded inside a message
does not count as one. Bounces written as text come before automatic replies because several servers mark their
bounces `Auto-Submitted: auto-replied`.

Then the rules are tried — see [Rules](rules.md).

## Delivery status notifications

The status part is read as groups of fields separated by empty lines. Each group with a `Final-Recipient`, or failing
that an `Original-Recipient`, is a recipient: its address — the domain in lowercase —, `Action`, `Status` (the code,
its comment dropped), `Diagnostic-Code`, the basic SMTP code at the start of the diagnostic, `Remote-MTA` and
`Last-Attempt-Date`. A missing status is looked for in the diagnostic. The report's kind is the worst of its
recipients: hard, then soft, then delivered; a notification without a recipient is of unknown kind.

## Bounces written as text

The text parts of the message, not those of the message it returns, up to the limit of text, are searched for the
first address that is not a mail server's own, the first enhanced status code, and the first SMTP code at the start of
a line or after a colon. Without a code, the kind is unknown and only a rule can tell; without an address, the report
names no recipient.

## Automatic replies

The recipient is the `From` of the reply; the message it answers is its `In-Reply-To`, unless it returns the message.

## The report

| Member | Holds |
|---|---|
| `format`, `kind`, `action` | How it was recognised, what it says, what to do — see [Kinds and actions](kinds-and-actions.md) |
| `message` | The `Mime\Part` of the whole message |
| `recipients` | Each `Recipient` the report names |
| `feedback` | The `FeedbackReport` of an abuse report |
| `original` | The `OriginalMessage` it is about — see [Finding the message](finding-the-message.md) |
| `verpTag` | The tag of the return path that received it, when a `Verp` is given |
| `rule` | The name of the rule that decided, or `null` |
| `authentic` | Whether the authenticator vouched for the abuse report |
| `isRecognised()` | Whether its kind is known |
| `addresses()` | The addresses of the recipients the message failed for, or of all of them when none failed, each once |
