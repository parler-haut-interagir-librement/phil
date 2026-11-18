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
keep, and when the session expired or is over.

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
| `200` with `errors`, `token`, `ended`, `next`, `exit` | The commit is kept: `errors` names each element left out with its error code; `token` is the token of the next commit, null once the session ended; `next` is the activity a navigation request goes to, `exit` whether it leaves for the outline |
| `400` | The body is not such an object |
| `403` | The CSRF token or the attempt token is refused: unknown, forged, expired, or another learner's |
| `409` | The session is over: it ended, or a later launch opened a newer one |
| `413` | The body is larger than 2 MiB |

The attempt token names the attempt, the session and the moment it expires, signed by an HMAC under
`phil_courses.scorm.secret`; it holds `phil_courses.scorm.token_ttl` seconds after the last commit, four hours by
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
the time allowed and what happens once it is spent. The exit and session time of the session before are forgotten.

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

## Packages of several SCOs

The SCOs of a package are the activities of the course, in the order of the organization the import read: the outline
is the learner's choice of what to take next. A SCO of SCORM 2004 may also ask where to go when its session ends:

| `adl.nav.request` | The page goes to |
|---|---|
| `continue`, `previous` | The next or previous SCORM activity of the course, else the outline |
| `{target=<item>}choice`, `{target=<item>}jump` | The activity imported from that item, else the outline |
| `exit`, `exitAll`, `suspendAll`, `abandon`, `abandonAll` | The outline |

`adl.nav.request_valid.continue` and `adl.nav.request_valid.previous` say whether there is a SCORM activity after or
before this one; `adl.nav.request_valid.choice` and `adl.nav.request_valid.jump` answer `unknown`.

## What is supported

| Part | State |
|---|---|
| The API of SCORM 1.2 (`API`) and of SCORM 2004 4th edition (`API_1484_11`), with their states and error codes | Supported |
| Every element of the data model of SCORM 1.2 and of SCORM 2004, their types, vocabularies, ranges, read and write rules, `_children`, `_count` and `_version` | Supported, checked in the browser and on the server |
| Objectives, interactions, their objectives and correct responses, comments of SCORM 2004, each collection within its smallest permitted maximum | Supported |
| The responses of the interactions of SCORM 2004 checked by the type of their interaction | `true-false`, `choice`, `sequencing`, `likert`, `matching` and `numeric`; `fill-in`, `long-fill-in`, `performance` and `other` bounded in length |
| `suspend_data`: 4 096 characters in SCORM 1.2, 64 000 in SCORM 2004 | Supported; the data of an attempt is bounded at 1 MiB in all |
| Session time added to total time; status and score rules of each version | Supported |
| Attempts: resume after a suspension, a new attempt after an ended one, tries of the activity | Supported |
| Mastery score, scaled passing score, completion threshold, launch data, time allowed and its action, from the manifest | Handed to the content |
| Navigation requests `continue`, `previous`, `choice`, `jump` and the exits | Supported, as above |
| The tracking data in the access and erasure requests of phil/privacy-bundle | Supported when that bundle is installed |

## What the bundle does not do yet

| Part | What happens instead |
|---|---|
| The sequencing and navigation of SCORM 2004 — sequencing rules, rollup, limit conditions, objectives shared between SCOs, the activity tree as one attempt | Kept with the activity for information; each SCO is an activity of the course, and the course's own order and requirements apply |
| The prerequisites of SCORM 1.2 | Kept for information; impose the order of the course instead |
| `adl.nav.request_valid.choice` and `.jump` for a target | Answer `unknown` |
| Review mode of an ended attempt | A launch after an ended attempt begins a new one |
| The time allowed enforced by the platform | Handed to the content, which applies its action |
| The formats of `fill-in`, `long-fill-in`, `performance` and `other` responses | Bounded in length, not parsed |
| Two commits of one session sent at once by two tabs | The last one kept wins |

## Privacy

The tracking data is the learner's: their answers to the interactions, their comments, what they wrote in a form of
the content. With phil/privacy-bundle installed, `ScormPrivacy` gives the attempts of a learner — what the platform read
of each and its tracking data — in an access request, and deletes them in an erasure request; the learner is the
stable identifier the courses know them by. The `ScormSessionEnded` event carries identifiers only.
