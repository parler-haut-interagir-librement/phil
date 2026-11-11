# Threats

A bounce mailbox receives mail from anyone. What a hostile message can attempt, and who stops it:

| Threat | Protection |
|---|---|
| A message built to exhaust memory or time: huge, deeply nested, thousands of parts or fields | `Limits` bounds octets, depth, parts, fields and text; the reader never throws; the fuzz target runs the parser on messages nobody wrote |
| A forged abuse report, to unsubscribe someone | A complaint unsubscribes only when the `Contract\ReportAuthenticator` vouches for it; `Auth\AuthenticationResults` trusts only the application's own server's DKIM results for the feedback loops' domains |
| A forged bounce, to count hard bounces against someone else's address | The application matches the report to a message it sent — VERP tag, `Message-ID`, its own field — and counts only then; a tag written by `Verp::encodeSigned()` and checked by `decodeSigned()` cannot be forged, while a classic VERP tag from `encodeRecipient()` can and must not decide a suppression |
| A forged envelope field, to make a bounce carry someone else's tag | The tag is read only from `Delivered-To`, `X-Original-To` and `Envelope-To`, which the receiving server adds, never from `To`; `envelopeFields` lists only the fields the application's own server writes |
| A rule's regular expression that backtracks without end | PCRE's backtracking limit stops it; a failed match does not match; the text a rule reads is bounded by `maxText` |
| An invalid regular expression in the configuration | Refused when the rule is declared |
| Personal data in logs | Exceptions name rules and limits, never addresses; the report holds addresses for the application, which keeps them out of its messages and logs |

What stays the application's: reading the mailbox with credentials it protects, deleting what it has processed,
matching reports to its sends, thresholds and suppression lists, and erasing the messages it keeps for review once a
person has read them.
