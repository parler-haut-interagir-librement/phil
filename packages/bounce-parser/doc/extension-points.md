# Extension points

The library is plain PHP and declares no service. What an application chooses goes through one port and arguments.

## `Contract\ReportAuthenticator`

| Method | Contract |
|---|---|
| `isAuthentic(Mime\Part $message, FeedbackReport $report): bool` | Says whether an abuse report comes from a feedback loop the application subscribed to; keeps no state between calls |

`Auth\AuthenticationResults` reads the `Authentication-Results` of the application's server; `Auth\TrustEveryReport`
trusts all. An adapter of the application may verify the DKIM signature itself, or check the sending IP against the
published ranges of its feedback loops — see [Feedback reports](feedback-reports.md).

## Arguments

| Point | How |
|---|---|
| What no standard describes | `Rule\Rules` and its rules, ordered by name — see [Rules](rules.md) |
| Finding the message sent | A `Verp` for the return paths, a field of the application's own read with `OriginalMessage::header()` — see [Finding the message](finding-the-message.md) |
| The bounds of the work | `Limits` — see [MIME](mime.md) |
| What each kind leads to | The `BounceAction` of the report, mapped by the application to its counters, suppression list and review queue |
| Catching the library's errors | Every exception implements `Exception\BounceParserException`; parsing a message throws none |
