# SCORM run time

A SCORM activity runs inside the platform: its content finds the run-time API of its version, reports where the learner
stands, and the platform keeps what it reports as the tracking data of an attempt. SCORM 1.2 content finds `API` and
calls `LMSInitialize()`, `LMSGetValue()`, `LMSSetValue()`, `LMSCommit()` and `LMSFinish()`; SCORM 2004 content finds
`API_1484_11` and calls `Initialize()`, `GetValue()`, `SetValue()`, `Commit()` and `Terminate()`. The data model is the
one of phil/elearning-packages, which checks every value in the browser and again on the server.

The run time is on by default. With `phil_courses.scorm.enabled: false`, a SCORM activity is framed without an API and
the learner marks it done.

## How a session runs

```text
activity page (application origin)            frame (content origin)              SCO (content origin)
  Stimulus controller scorm-runtime   <-- postMessage -->   API / API_1484_11   <-- window.parent --  content
  POST /courses/scorm/commit, sendBeacon
```

1. The learner launches the activity. The bundle opens a session of an attempt and renders the activity page, which
   holds the Stimulus controller `phil--courses-bundle--scorm-runtime`, the attempt token, and the state of the session:
   the tracking data it begins with and the data model.
2. The controller frames `<content base URL>/_phil/scorm/frame.html`, the frame of the run time, served on the content
   origin. Once the frame says it is ready, the controller hands it the state — by `postMessage()`, to the content
   origin alone.
3. The frame puts the API of the version on its window, then frames the SCO, on the same origin: the SCO finds the API
   in its parent window, as SCORM asks. The frame accepts messages from the application's origin alone, which
   `phil_courses.content.application_origin` names, and only the application's origin may frame it.
4. Each call answers at once from the values the frame keeps, with the error code of the version. Each value the
   content writes is handed to the controller; `LMSCommit()` or `Commit()` sends them to the commit endpoint, in the
   order they were written; `LMSFinish()` or `Terminate()` sends the rest and ends the session.
5. When the learner leaves the page without the content ending the session, the controller sends what is left with
   `navigator.sendBeacon()`, which ends the session too.
6. Each answer of the endpoint brings the token of the next commit. A quiet session commits nothing every ten minutes,
   to keep its token alive.

The page says, in a live region, whether the progress is saved, whether the content sent values the platform did not
keep, when the session expired or is over, when the time allowed is spent, and that an attempt opened for review keeps
nothing.

## The commit endpoint

`POST /courses/scorm/commit`, under the prefix of `config/routes.php`, takes a JSON object:

| Key | Holds |
|---|---|
| `token` | The attempt token the page was given, or the last answer gave |
| `csrf` | The stateless CSRF token of the forms of the courses |
| `values` | The elements the content wrote since the last commit, in the order it wrote them, each with its value as text or a number |
| `finish` | Whether the session ends |

| Answer | When |
|---|---|
| `200` with `errors`, `token`, `ended`, `next`, `exit`, `remaining`, `time_up` | The commit is kept: `errors` names each element left out with its error code; `token` is the token of the next commit, null once the session ended; `next` is the activity a navigation request goes to, `exit` whether it leaves for the outline; under a time limit, `remaining` is the seconds left before the deadline, and `time_up` says the time ended the attempt — see [Time limits](#time-limits) |
| `400` | The body is not such an object |
| `403` | The CSRF token or the attempt token is refused: unknown, forged, expired, or another learner's |
| `409` | The session is over: it ended, or a later launch opened a newer one; or its attempt ended, and opens for review only |
| `413` | The body is larger than 2 MiB |

The attempt token names the attempt, the session and the moment it expires, signed by an HMAC under a key
derived from `phil_courses.scorm.secret` by HKDF-SHA256 for that usage alone (`ScormTokens::USAGE`); it holds `phil_courses.scorm.token_ttl` seconds after the last commit, four hours by
default. When a learner is signed in, the attempt must be theirs; a beacon sent as the learner signs out is still
taken, on its token alone.

## Attempts

An attempt keeps the tracking data of a learner at an activity, from session to session.

| The last attempt | The next launch |
|---|---|
| None | A new attempt, `cmi.entry` is `ab-initio` |
| Suspended: the content set `cmi.core.exit` or `cmi.exit` to `suspend` | The same attempt resumed, `cmi.entry` is `resume`, its data kept |
| SCORM 1.2, ended neither completed, passed nor failed, and not suspended | The same attempt, `cmi.entry` is empty, its data kept |
| Ended: any other end of a session of SCORM 2004; a status of `completed`, `passed` or `failed` in SCORM 1.2 | A new attempt, `ab-initio`, its data empty |
| A session still running — a page closed before it could say so | That session is ended first, then the rules above apply |

Each launch opens the next session of its attempt and gives it a new token: the page of an earlier launch commits no
more, and receives `409`.

When a session begins, the platform sets the learner's identifier — never a name or an e-mail address —, the credit,
the mode `normal`, the entry, and what the manifest says of the item: the launch data, the mastery score of SCORM 1.2
(`cmi.student_data.mastery_score`, out of a hundred) or the scaled passing score of SCORM 2004
(`cmi.scaled_passing_score`, from the primary objective satisfied by measure), the completion threshold of SCORM 2004,
the time allowed and what happens once it is spent — the shorter of the manifest's and the activity's own, see
[Time limits](#time-limits). The exit and session time of the session before are forgotten.

When a session ends, the session time is added to the total time of the attempt — `HHHH:MM:SS.SS` in SCORM 1.2, a
duration of ISO 8601 in SCORM 2004. In SCORM 1.2, a status the content never set becomes `completed`, and with a
mastery score and a raw score in credit mode, the status becomes `passed` or `failed` by the score. In SCORM 2004, the
completion is evaluated against the completion threshold and the progress measure, and the success against the scaled
passing score and the scaled score, as `GetValue()` answers them.

## Progress

The attempt feeds the progress of the learner in the activity, the same as any other activity type:

- once the content reports the activity completed or passed, a commit records it: `ActivityProgressed`, then
  `ActivitySatisfied` and `CourseCompleted` when the requirement of the activity and the course are met;
- the end of a session records the whole outcome: completed, passed or failed, and the score — the scaled score of
  SCORM 2004, else the raw score within its minimum and maximum, out of a hundred in SCORM 1.2 when they are not set;
- a failure counts once the attempt ends, so that each failed attempt uses one try of `max_attempts`; a learner out of
  tries asks for a second chance, as for any activity; an attempt left incomplete uses no try;
- a session without credit, or in browse or review mode, records nothing.

The requirement of an imported SCORM activity is `Passed` when its item has a mastery score or a scaled passing score,
`CompletedOrPassed` otherwise. A completion or a pass is never undone by a later attempt.

`ScormSessionEnded` is dispatched at the end of each session, with the enrollment, the activity and the attempt by
their identifiers, and whether the attempt ended.

## Review mode

An ended attempt opens again for review. The outline shows **Review the last attempt** beside a SCORM activity whose
learner has an ended attempt, once the activity is reached — open, done, or out of tries —; the form posts to
`POST /{course}/activities/{activity}/review`, with the CSRF token of the courses, and the activity page frames the
content as for a launch, with a badge that says it is a review.

- The content reads the tracking data of the learner's last ended attempt, with the mode `review`
  (`cmi.core.lesson_mode` in SCORM 1.2, `cmi.mode` in SCORM 2004), the credit `no-credit` and an empty entry; the
  exit and session time of its last session are forgotten, as at any launch.
- The content may still call `SetValue()`: the run time answers it as in any session, and keeps the value in the frame
  for the content to read back, but the page sends nothing — no commit, no beacon at the end — and says so in its live
  region.
- No session is opened, the attempt is left as it was, and the page is given an empty token. On the server, a commit to
  an ended attempt is refused with `409`, whatever token it carries: neither the tracking data nor the progress change.
- A review records nothing, dispatches no event and uses no try.

`ScormRuntime::review()` opens the review, and `ScormRuntime::reviewable()` gives the activities of an enrollment
that have an ended attempt. The values the review hands the content, and what the content's calls answer, are held by
the vectors of `tests/js/fixture/review.json`, which the server and the browser both run.

## Time limits

An attempt is allowed the time of the manifest — `adlcp:maxtimeallowed` in SCORM 1.2, the
`attemptAbsoluteDurationLimit` of SCORM 2004 — with its action, `time_limit_action`, and the activity's own limit, its
`time_limit` setting in seconds, from 1 to 35 999 999:

| The limits | The content is handed | The platform ends the attempt |
|---|---|---|
| The manifest's only, its action `continue,…` or none | That time and that action | Never: the content applies its action |
| The manifest's only, its action `exit,…` | That time and that action | At that time |
| The activity's only, or shorter than the manifest's, or as long | The activity's time, and the manifest's action when it exits, else `exit,message` | At the activity's time |
| The manifest's shorter than the activity's | The manifest's time and action | At the manifest's time when its action exits, else at the activity's time |

The platform counts the time by its own clock, whatever the content reports in its session time: the seconds each
session ran, from its launch to its end. The time between two sessions is not counted, and a session closed without
a word — the page closed before it could say so — counts until its last commit. The page commits when the deadline
comes, and each answer brings the seconds left.

- A commit that reaches the deadline is kept, then ends the session with the exit `time-out`, and the attempt with
  it, even when the content asked to suspend: the answer says `time_up`.
- The commit of a page late by more than 30 seconds, `ScormRuntime::GRACE`, is not kept: the session ends with the data
  of the commits before, and the answer says `time_up`.
- A session left running past its deadline is ended at the next launch, with the exit `time-out`; an attempt suspended
  past it — when the limit was lowered — ends at the next launch too.
- An attempt ended by its time and neither completed nor passed counts as failed: it uses a try of `max_attempts`.

The time a session ran is kept with its attempt, in the columns `elapsed` and `session_opened_at` of
`phil_course_scorm_attempt`, which `CourseTables::upgrade()` adds to an earlier table.

## Packages of several SCOs

The SCOs of a package are the activities of the course, in the order of the organization the import read: the outline
is the learner's choice of what to take next. A SCO of SCORM 2004 may also ask where to go when its session ends:

| `adl.nav.request` | The page goes to |
|---|---|
| `continue`, `previous` | The next or previous SCORM activity of the course, else the outline |
| `{target=<item>}choice`, `{target=<item>}jump` | The activity imported from that item, else the outline |
| `exit`, `exitAll`, `suspendAll`, `abandon`, `abandonAll` | The outline |

`adl.nav.request_valid.continue` and `adl.nav.request_valid.previous` say whether there is a SCORM activity after or
before this one. `adl.nav.request_valid.choice.{target=<item>}` and `adl.nav.request_valid.jump.{target=<item>}` answer
`true` when an activity of the course was imported from that item, and `false` for any other target: the page hands
the content the targets of the first 500 such activities of the course, `ScormRuntime::MAX_TARGETS`, with the session,
and never keeps them with the attempt. The activity the request goes to still applies its own availability: an
activity released later or waiting for another one shows why on its page.

## What is supported

| Part | State |
|---|---|
| The API of SCORM 1.2 (`API`) and of SCORM 2004 4th edition (`API_1484_11`), with their states and error codes | Supported |
| Every element of the data model of SCORM 1.2 and of SCORM 2004, their types, vocabularies, ranges, read and write rules, `_children`, `_count` and `_version` | Supported, checked in the browser and on the server |
| Objectives, interactions, their objectives and correct responses, comments of SCORM 2004, each collection within its smallest permitted maximum | Supported |
| The responses of the interactions of SCORM 2004 checked by the type of their interaction | Every type: `fill-in` and `long-fill-in` with their languages and `{case_matters}` and `{order_matters}`, `performance` with its steps and ranges, each within its smallest permitted maximum; `other` bounded in length |
| `suspend_data`: 4 096 characters in SCORM 1.2, 64 000 in SCORM 2004 | Supported; the data of an attempt is bounded at 1 MiB in all |
| Session time added to total time; status and score rules of each version | Supported |
| Attempts: resume after a suspension, a new attempt after an ended one, tries of the activity | Supported |
| Mastery score, scaled passing score, completion threshold, launch data, time allowed and its action, from the manifest | Handed to the content |
| Review mode of an ended attempt | Supported: read only, nothing kept, commits refused on the server |
| The time allowed, from the manifest or the activity | Enforced by the platform, by its own clock |
| Navigation requests `continue`, `previous`, `choice`, `jump` and the exits, and whether each is valid | Supported, as above |
| The tracking data in the access and erasure requests of phil/privacy-bundle | Supported when that bundle is installed |

## What the bundle does not do yet

The full sequencing and navigation of SCORM 2004 is a feature of its own, planned apart from the run time: until it
comes, each SCO is an activity of the course.

| Part | What happens instead |
|---|---|
| The sequencing and navigation of SCORM 2004 — sequencing rules, rollup, limit conditions, objectives shared between SCOs, the activity tree as one attempt | Kept with the activity for information; each SCO is an activity of the course, and the course's own order and requirements apply |
| The prerequisites of SCORM 1.2 | Kept for information; impose the order of the course instead |
| Two commits of one session sent at once by two tabs | The last one kept wins |

## Privacy

The tracking data is the learner's: their answers to the interactions, their comments, what they wrote in a form of
the content. With phil/privacy-bundle installed, `ScormPrivacy` gives the attempts of a learner — what the platform read
of each and its tracking data — in an access request, and deletes them in an erasure request; the learner is the
stable identifier the courses know them by. The export holds the time the platform measured and the exit `time-out` of
an attempt ended by its time. The `ScormSessionEnded` event carries identifiers only. The cmi5 records of a learner
have their own privacy module: see [cmi5](cmi5.md#privacy).
