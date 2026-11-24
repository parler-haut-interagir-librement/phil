# Responses

`Submissions::submit($questionnaire, $input, $respondent, $subject)` takes a response. The server judges it whatever the
browser showed:

1. the questionnaire is open — its window and, for a session's feedback, the session's window — and the respondent may
   answer: a member when it needs one, once when the respondent is known;
2. `phil/form-logic` judges the answer: the hidden questions are dropped, unchecked, and the computed variables are
   computed;
3. each question shown checks its answer; `InvalidResponse` carries every error at once, by question name;
4. the first ending whose condition holds is chosen, and the quotas the answer meets are found;
5. the response, the respondent's mark and the quotas' counters are written in one transaction.

A refusal throws `SurveyRefused`, whose `reason` is a key the templates translate: `closed`, `already_answered`,
`member_required`, `subject_required`, `results_private`, `addenda_closed`, `unknown_receipt` and `untrusted_request`.

The `Receipt` holds the ending reached, whether the response was kept, its identifier, the redirect, the computed
variables, the quota that screened it out if any, and the addendum token when the questionnaire takes addenda.

## Endings and profiles

The endings are tried in order and the first whose `when` holds is shown; the last has no condition, so every respondent
reaches one. An ending may name a `profile`, the result a respondent is given, such as a score band, and a `message`.

An ending may also `redirect`. The address is read by `phil/url`, and its scheme, host and port must be one of
`redirect_origins`; `localhost` is a host only when `allow_localhost` is on. It is checked when the questionnaire is
saved and again when the respondent is sent there, so a questionnaire saved before an origin was removed redirects
nowhere. The bundle never redirects to an address the request carries.

## Quotas

A quota takes `limit` responses meeting its condition. A cross quota is a condition over several questions:

```json
{"name": "young_north", "limit": 50, "ending": "full", "when": {"and": [{"==": [{"var": "age"}, "18-24"]}, {"==": [{"var": "region"}, "north"]}]}}
```

A response counts in every quota it meets. The counts are atomic counters, one row per questionnaire and quota,
increased by a conditional update — `taken < limit` — in the response's transaction, never computed by counting
responses. When one of them is full, the transaction is rolled back: the response is not kept, and the respondent sees
that quota's ending. The counters are created outside the transaction, and a deadlock is retried. The integration tests
run concurrent processes against one quota on MySQL and PostgreSQL and check that it never takes more than its limit.

## Anonymity

The page says whether a questionnaire is anonymous before its first question. An anonymous response keeps:

- no respondent and no IP address — the tables have no column for one;
- a random identifier, a UUID v4, which holds no time;
- the day it was sent, at midnight UTC, and not the time.

So that a member answers once, the bundle keeps a mark of participation apart: the questionnaire, the subject and a
keyed SHA-256 digest of the member's identifier under `secret`. The mark has no time and no reference to the response,
so nothing joins the two. An identified response keeps the member's identifier; it is the only kind the privacy export
and erasure find.

## Session feedback

A `session_feedback` questionnaire takes responses about a subject, given as the `subject` query or form value. The
`FeedbackSessions` service of the application gives each session's start and end; the feedback opens `opens_before`
minutes before the start and closes `closes_after` minutes after the end. Without a `FeedbackSessions` service, no
session feedback is open. It is anonymous by default, as every questionnaire.

## Addenda

When `addenda` is on, the receipt carries a token of 32 random bytes that only the respondent holds; the bundle keeps
its SHA-256. `Addenda::add($questionnaire, $token, $text)` adds a text of at most 5000 characters to the response, up to
ten per response, each with the instant it was added.
