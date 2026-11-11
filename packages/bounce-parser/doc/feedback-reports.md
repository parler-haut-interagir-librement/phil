# Feedback reports

An abuse report (RFC 5965) is what a mailbox provider sends through a feedback loop when a recipient marks a message
as spam. The parser reads its `message/feedback-report` part into a `FeedbackReport`.

| Member | Field |
|---|---|
| `type` | `Feedback-Type`, a `FeedbackType`: `Abuse`, `Fraud`, `Virus`, `Other` (and any type the list does not know), `NotSpam`, `AuthFailure` |
| `userAgent`, `version` | `User-Agent`, `Version` |
| `originalMailFrom` | `Original-Mail-From`, the address alone |
| `originalRcptTo` | Each `Original-Rcpt-To`, the address alone; providers often leave them out |
| `arrivalDate` | `Arrival-Date`, else `Received-Date`, as written |
| `reportingMta` | `Reporting-MTA`, without its type |
| `sourceIp` | `Source-IP` |
| `incidents` | `Incidents`, 1 when missing or not a number |
| `reportedDomain`, `reportedUri` | Each `Reported-Domain`, `Reported-URI` |
| `authenticationResults` | The `Authentication-Results` the provider wrote in the report |

The recipients of the report are its `Original-Rcpt-To`, or the `To` of the message it returns when it has none. Many
providers hide both: the application then finds the recipient from the message, by its `Message-ID`, its
`Feedback-ID`, a field of its own, or its VERP tag — see [Finding the message](finding-the-message.md).

## Authentication

Anyone can write an abuse report to the bounce mailbox, and a complaint unsubscribes. The parser therefore asks a
`Contract\ReportAuthenticator` whether the report comes from a feedback loop the application subscribed to;
`BounceReport::$authentic` holds the answer. Without an authenticator, or when it does not vouch, a complaint goes to
`BounceAction::Review`.

`Auth\AuthenticationResults` vouches for a report whose message carries an `Authentication-Results` field (RFC 8601)
written under one of the application's authserv-ids — the name its own mail server writes its results under — that
records `dkim=pass` with a `header.d` that is one of the trusted domains, or a subdomain of one. Fields of other
authserv-ids are ignored: anyone can write one. The mail server must remove, from the messages it receives, every
field that claims its own authserv-id, as RFC 8601 asks; most servers do it, check yours.

`Auth\TrustEveryReport` vouches for every report: for a mailbox only the feedback loops can write to, its address
known to nobody else.
