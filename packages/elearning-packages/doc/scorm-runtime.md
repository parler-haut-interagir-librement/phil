# The SCORM run-time data model

`Phil\ElearningPackages\Runtime` holds the data model a SCORM run time checks the content's calls against: the elements
of SCORM 1.2 (CMI data model 3.4) and of SCORM 2004 4th edition, their types, vocabularies, ranges and read and write
rules, the error codes of each version, the run-time API with its states, and what the platform does when a session
begins and ends. It is plain PHP and keeps no state between calls: a platform builds its run time on it, whatever
carries the calls — a page that relays the browser's API, a test, a migration tool that replays tracking data.

```php
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\Entry;
use Phil\ElearningPackages\Runtime\LaunchSettings;
use Phil\ElearningPackages\Runtime\ScormVersion;

$data = new CmiData(ScormVersion::Scorm2004, $kept);       // the values an earlier commit kept
CmiAttempt::begin($data, new LaunchSettings('learner-42', masteryScore: 0.8), Entry::Resume);

$errors = CmiCommit::apply($data, $valuesFromTheBrowser); // ['cmi.learner_id' => 404], the others kept
$end = CmiAttempt::end($data);                            // total time, status, suspended or ended

$end->status->completion;  // 'completed'
$end->status->success();   // true
$end->status->score;       // 0.9
$end->attemptEnded;        // true: the next launch begins a new attempt
$store->save($data->values());
```

## The elements

`DataModel::definitions()` gives every element of a version, by name, each index of a collection written `n`:
`cmi.interactions.n.id`. An `ElementDefinition` says who may read and write it (`Access`), and what its value may be: a
regular expression its whole value matches, a number of characters at most, a range for a number, and what it reads
before it is set. The bounds are the smallest permitted maximums of each specification:

| | SCORM 1.2 | SCORM 2004 |
|---|---|---|
| `suspend_data` | 4 096 characters | 64 000 characters |
| Location | `cmi.core.lesson_location`, 255 | `cmi.location`, 1 000 |
| Objectives, interactions | 100, 250 | 100, 250 |
| Objectives of an interaction, correct responses | 10, 10 | 10, by type of interaction: 1 to 10 |
| Comments of the learner, of the platform | `cmi.comments`, 4 096 characters | 250, 100 |
| Times | `CMITime` `HH:MM:SS.SS`, `CMITimespan` `HHHH:MM:SS.SS` | `time` of ISO 8601 from 1970 to 2038, `timeinterval` of ISO 8601 |
| Scores | raw, minimum and maximum from 0 to 100, or blank | scaled from -1 to 1; raw, minimum and maximum any real |

The responses of the interactions of SCORM 2004 are checked by the type of their interaction:
`DataModel::responsePattern()` gives the pattern of `true-false`, `choice`, `sequencing`, `likert`, `matching` and
`numeric`; the responses of `fill-in`, `long-fill-in`, `performance` and `other` are bounded in length.

The patterns are written so that PHP, with the `u` modifier, and JavaScript, with the `u` flag, read them alike.
`DataModel::describe()` gives the data model as the browser reads it — the elements, the collections and their bounds,
the elements that answer `_children`, the correct responses and the patterns of the responses, and the messages of the
error codes —, so that a run time in the browser answers a call as the server will.

## Reading and writing

`CmiData` holds the values of an attempt, by element. `get()` and `set()` answer as `GetValue()` and `SetValue()` do: a
`RuntimeResult` with the value read, and the error code of the version, `0` when none, with a short diagnostic.

- An element the data model does not define, a read of a write-only element, a write of a read-only element or of a
  keyword are refused; `_children`, `_count` and `cmi._version` are answered.
- A collection grows one element at a time: an index past the next free one is refused, and so is one past the bound.
- In SCORM 2004, an element of an objective or an interaction needs its identifier first, and a response the type of
  its interaction (`408`); the identifier of an objective is unique and never changes, and so is that of an objective
  of an interaction among the others of the interaction (`351`).
- A value is checked against the pattern, the length and the range of its element; a number is written as its text.
- An element never set reads as the empty string in SCORM 1.2, and answers `403` in SCORM 2004 unless it has a default:
  `cmi.completion_status` and `cmi.success_status` read `unknown`, `cmi.total_time` `PT0H0M0S`.
- In SCORM 2004, `cmi.completion_status` is evaluated against `cmi.completion_threshold` and `cmi.progress_measure`, and
  `cmi.success_status` against `cmi.scaled_passing_score` and `cmi.score.scaled`, when the platform sets them.
- The data of an attempt is bounded in bytes as a whole, 1 MiB by default: a write past it is refused.

`put()` is the platform's own write — `cmi.core.student_id`, `cmi.total_time` —, which checks that the element exists,
not its access. `values()` gives what to keep; a new `CmiData` loads it back, and refuses with `RuntimeRefused` a value
that is not text or an element the version does not define.

`CmiCommit::apply()` applies a commit — the elements written since the last one, in the order they were written — as
`set()` would, keeps the values the data model accepts, and gives the others with their error codes. A commit names
`CmiCommit::MAX_VALUES` elements at most.

## The error codes

`ScormError` holds the codes of each version and their messages: `0`, `101`, `201`, `202`, `203`, `301` and `401` to
`405` in SCORM 1.2; `0`, `101` to `104`, `111` to `113`, `122`, `123`, `132`, `133`, `142`, `143`, `201`, `301`, `351`,
`391` and `401` to `408` in SCORM 2004. The same mistake has another number in each: `ScormError::for()` gives the
number of a `Mistake` in a version.

| Mistake | SCORM 1.2 | SCORM 2004 |
|---|---|---|
| Undefined element | 401 | 401 |
| Read-only element written, keyword written | 403, 402 | 404 |
| Write-only element read | 404 | 405 |
| Wrong type, out of range | 405 | 406, 407 |
| Dependency not established | 405 | 408 |
| Index past the next, collection full | 201, 101 | 351 |
| Index past the count read | 201 | 301 |
| `_children` or `_count` of an element that has none | 202, 203 | 301 |
| Element without a value read | the empty string, `0` | 403 |

## The API

`ScormSession` answers the calls of one session as `LMSInitialize()`… or `Initialize()`… would: `"true"` or `"false"`,
the value read, and `getLastError()`, `getErrorString()` and `getDiagnostic()`. A call before `initialize()` or after
`terminate()` is refused — `301` in SCORM 1.2; `122`, `123`, `132`, `133`, `142`, `143`, `112`, `113` in SCORM 2004 —,
an argument that is not the empty string answers `201`, a second `initialize()` `101` or `103`, an `initialize()` after
`terminate()` `101` or `104`. `commit()` and `terminate()` call the closures given, which keep the data, and answer
`101`, `391` or `111` when they say they could not.

## A session's beginning and end

`CmiAttempt::begin()` sets what the platform hands a SCO, from `LaunchSettings`: the learner's identifier and name, the
credit, the mode, the entry (`Entry`), the launch data, the mastery score — `cmi.student_data.mastery_score` out of a
hundred in SCORM 1.2, `cmi.scaled_passing_score` in SCORM 2004 —, the completion threshold of SCORM 2004, the time
allowed and its action; it forgets the exit and session time of the session before, and sets SCORM 1.2's
`cmi.core.lesson_status` to `not attempted` on a first session.

`CmiAttempt::end()` adds the session time to the total time, as each version writes it (`Duration`: hundredths of a
second, a year of ISO 8601 counted as 365 days and a month as 30, bounded at 9 999 hours). In SCORM 1.2, a status the
content never set becomes `completed`, and with a mastery score and a raw score in credit mode, the status becomes
`passed` or `failed` by the score. An exit of `suspend` suspends the attempt; otherwise SCORM 2004 ends it, and SCORM 1.2
ends it once completed, passed or failed. The `SessionEnd` gives the status, the total time, whether the attempt is
suspended or ended, and the navigation request of SCORM 2004, if any.

`RuntimeStatus` reads where the learner stands in the words of SCORM 2004 whatever the version — the `lesson_status` of
SCORM 1.2 as ADL maps it: `passed` is completed and passed, `failed` completed and failed, `browsed` incomplete — with
the score scaled from -1 to 1 by `Score`: the scaled score when set, else the raw score within its minimum and maximum,
out of a hundred in SCORM 1.2 when they are not set.

## What the data model leaves to the platform

- The sequencing and navigation of SCORM 2004: `adl.nav.request` is kept and given back by `SessionEnd`; the validity
  of the requests, `adl.nav.request_valid.*`, is the platform's to set with `put()`.
- Where the values are kept, the attempts and their numbers, the progress of the learner in a course.
- The formats of the responses of `fill-in`, `long-fill-in`, `performance` and `other` interactions, bounded only.
