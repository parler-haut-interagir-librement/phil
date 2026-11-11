# Reference

| Type | Role |
|---|---|
| `BounceParser` | Reads a message of the bounce mailbox and returns a `BounceReport` |
| `BounceReport` | What one message says: format, kind, action, recipients, feedback, original message, VERP tag, rule |
| `ReportFormat` | How the message was recognised |
| `BounceKind` | What the message says of the message sent |
| `BounceAction` | What the application should do, and `for()` the action of each kind |
| `Recipient` | One recipient a report names, and `kindOfFailure()` |
| `DeliveryAction` | The `Action` of a recipient of a delivery status notification |
| `StatusCode` | An enhanced mail system status code |
| `FeedbackReport` | The fields of an abuse report |
| `FeedbackType` | The `Feedback-Type` of an abuse report |
| `OriginalMessage` | The header fields of the message a report is about |
| `Verp` | Variable envelope return paths |
| `Limits` | The bounds of the work on one message |
| `Rule\Rule` | A rule: a pattern on a field, a kind, an action, a scope |
| `Rule\Rules` | The rules in their order |
| `Rule\Field` | The text a rule looks at |
| `Rule\RuleScope` | The messages a rule is tried on |
| `Contract\ReportAuthenticator` | The port that vouches for abuse reports |
| `Auth\AuthenticationResults` | Vouches by the `Authentication-Results` of the application's server |
| `Auth\TrustEveryReport` | Vouches for every report |
| `Mime\MimeParser` | Reads a message and its MIME structure |
| `Mime\Part` | A message or one of its parts |
| `Mime\Headers` | The header fields of a part |
| `Exception\BounceParserException` | The interface of every exception of the library |
| `Exception\InvalidLimits`, `Exception\InvalidRule`, `Exception\InvalidVerp` | A configuration refused |
| `Mime\PartBudget`, `Reader\*` | The parts left to read and the readings of each format: not part of the public API |
