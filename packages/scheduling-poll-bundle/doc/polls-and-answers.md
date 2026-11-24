# Polls and answers

`SchedulingPolls` is the service of the application: it creates the polls, enrolls the respondents, opens their links
and records their answers. It keeps no state between two calls and is safe in a long-running worker.

## Creating a poll

```php
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;

$poll = $polls->create(new SchedulingPollDraft(
    'Game night',
    [$slot1, $slot2, $slot3],                       // ProposedSlot: a start and an end, excluded, in any time zone
    'America/Toronto',                              // the time zone the dates are shown in
    new DateTimeImmutable('2026-11-28 12:00 UTC'),  // the deadline
    description: 'Bring dice.',
    location: 'The back room',
    organizerSeesAnswers: true,
    results: ResultsVisibility::Always,
), ResourceReference::of('user', 'ada'));
```

| Value | Bounds |
|---|---|
| Title | 1 to 200 characters, trimmed |
| Description, place | 4000 and 255 characters at most; blank is none |
| Dates | 2 to 100, each ending after it starts and at most fourteen days later, no two starting at the same moment |
| Time zone | A name of the IANA database, such as `America/Toronto` |
| Deadline | After the creation |

A value out of bounds throws `InvalidSchedulingPoll`, whose message says which bound. The instants are kept in UTC and
compared by order, never by equality; the time zone only shows them. The organizer is a reference of `phil/resource`:
only they invite, issue links again, revoke, close and see the answers of all.

## Respondents and their links

A respondent joins from the poll's page — the route `phil_scheduling_poll_join` — by their name alone, or is invited by
the organizer:

```php
$issued = $polls->join($poll->id, 'Bob');                                  // from the poll's page
$issued = $polls->join($poll->id, 'Bob', 'bob@example.org', $account);     // an address, an account: both optional
$issued = $polls->invite($poll->id, $organizer, 'Carol', 'carol@example.org', 'fr');

$issued->url;      // the personal link, to show or send
```

| Value | Bounds |
|---|---|
| Name | 1 to 100 characters; control and format characters become spaces, the rest is trimmed |
| Address | Optional; an address a mail server can deliver to, read by `phil/email-address`, its domain in ASCII |
| Account | Optional; a reference of `phil/resource` |
| Locale | Optional; a language tag, `fr` or `fr_CA`, the language of the mail |

A poll closed, past its deadline, or that has `max_respondents` already, refuses a new respondent with `AnswerRefused`.
A name or an address refused leaves no invitation behind.

The link is an invitation of `phil/access-links` to the campaign `scheduling-poll:<poll>`, its recipient the
respondent's identifier: no name and no address reach the library. Its token, 256 bits, is shown once in `IssuedLink`
and kept only as its keyed hash. The link changes the answers until the deadline, and still opens, to show the date
chosen, for `link_lifetime` after it.

```php
$access = $polls->open($token);     // RespondentAccess, or null when the link opens nothing
$access->respondent;
$access->poll;
$access->slots;
$access->editable;                  // whether the answers can still change

$again = $polls->reissue($poll->id, $organizer, $respondentId);   // a lost link; the earlier ones keep opening
$polls->revoke($poll->id, $organizer, $respondentId);             // its links open nothing, its answers are not counted
```

Opening a link changes nothing: a mail client that previews it answers nothing. A link malformed, unknown, revoked or
expired opens nothing, and the page does not say which.

## Answers

Each date is answered `Availability::Yes`, `IfNeedBe` or `No`:

```php
use Phil\SchedulingPollBundle\Domain\Availability;

$polls->answer($token, [
    $slot1 => Availability::Yes,
    $slot2 => Availability::IfNeedBe,
], 'Europe/Paris');                 // the time zone the respondent reads the dates in; the poll's when null
```

A date left out is answered no; a date the poll does not propose, or a time zone the IANA database does not have, throws
`InvalidSchedulingPoll`. Answering again replaces every answer. The answers are written in one conditional `UPDATE`,
only while the respondent is not revoked and the poll is neither closed nor past its deadline, so that an answer racing
the closing is kept before it or refused with `AnswerRefused`.

`AnswerForm::answers($request->request->all())` reads what the form posts — `answers[<slot>]` set to `yes`,
`if_need_be` or `no` — and refuses anything else; `AnswerForm::timezone()` reads `timezone`.

## Time zones

Every date is shown in the viewer's time zone: the one the page asks for, else the one the respondent last chose with
their answers, else the poll's. A zone the IANA database does not have is passed over. The changes of the hour fall
where they fall: a date at 19:00 in Toronto on 31 October 2026 is at 00:00 in Paris, a week later at 01:00.

## Who sees the answers

The views of `SchedulingPollViews` apply the poll's two choices:

| Viewer | Before the closing | Once closed |
|---|---|---|
| The organizer | When `organizerSeesAnswers` is true | Always |
| A respondent, `ResultsVisibility::Always` | Always | Always |
| A respondent, `AfterVoting` | Once they answered | Always |
| A respondent, `AfterClosing` | Never | Always |
| A respondent, `Managers` | Never | Never |

```php
$sheet = $views->sheet($token, $timezone);              // AnswerSheet, or null: the respondent's page
$results = $views->results($poll->id, $organizer);      // ResultsView: the organizer's table
```

A view whose answers are hidden has `visible` false, no counts and no rows, and says how many answered. The rows are
the respondents who answered and were not revoked, numbered in the order they joined; an erased name is `null`.

There are no comments on a poll: a page that wants them uses a moderated module, such as `phil/comments-bundle`.
