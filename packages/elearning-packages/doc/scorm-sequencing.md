# SCORM sequencing

SCORM 2004 lets a course say how a learner moves through it: which activities may be chosen, which follow by flow,
when one is skipped or disabled, how the results of the children roll up to their parent, which objectives are shared
between activities. This is IMS Simple Sequencing, as SCORM 2004 4th edition applies it. SCORM 1.2 has a smaller
device: the prerequisites of an item.

The library reads both into an immutable activity tree, and runs them with a sequencer: a deterministic state machine
that takes a state, a navigation request, and returns a new state with the activity to launch. It holds nothing between
two requests; the platform keeps the state.

## Reading the activity tree

```php
use Phil\ElearningPackages\Sequencing\SequencingReader;

$tree = new SequencingReader()->read($manifestXml);           // the default organization
$tree = new SequencingReader()->read($manifestXml, 'org-2');  // another one

$tree->root;              // the organization, as an Activity
$tree->get('quiz');       // an item, by identifier
$tree->path('quiz');      // from the root down to it
$tree->warnings;          // what the reader set aside
```

The manifest is first read and checked by `ScormManifestReader`, within the same `PackageLimits`. Each `Activity` holds
its identifier, title, resource, visibility, children, its `Sequencing`, and for SCORM 1.2 its `Prerequisites`.

### What is read for SCORM 2004

The `<imsss:sequencing>` of the organization and of each item. When it names an entry of the
`<imsss:sequencingCollection>` by `IDRef`, the entry gives each element the item does not declare.

| Element | Value |
|---|---|
| `<imsss:controlMode>` | `ControlMode`: `choice`, `choiceExit`, `flow`, `forwardOnly`, `useCurrentAttemptObjectiveInfo`, `useCurrentAttemptProgressInfo` |
| `<imsss:sequencingRules>` | `SequencingRule` lists by `RuleTiming` — pre-condition, exit and post-condition rules —, each with its `RuleCondition` list, `all` or `any`, and its `RuleAction` |
| `<imsss:limitConditions>` | `LimitConditions`: `attemptLimit` — `0` reads as no limit — and `attemptAbsoluteDurationLimit` |
| `<imsss:rollupRules>` | `RollupRule` list, and in `RollupControls` the `rollupObjectiveSatisfied`, `rollupProgressCompletion` and `objectiveMeasureWeight` |
| `<adlseq:rollupConsiderations>` | In `RollupControls`: `requiredForSatisfied`, `requiredForNotSatisfied`, `requiredForCompleted`, `requiredForIncomplete` (`Requirement`) and `measureSatisfactionIfActive` |
| `<imsss:objectives>` | `Objective` list, the primary one first: `objectiveID`, `satisfiedByMeasure`, `minNormalizedMeasure`, and each `<imsss:mapInfo>` as an `ObjectiveMap` to a global objective |
| `<imsss:randomizationControls>` | `RandomizationControls`: `selectionTiming`, `selectCount`, `randomizationTiming`, `reorderChildren` |
| `<imsss:deliveryControls>` | `DeliveryControls`: `tracked`, `completionSetByContent`, `objectiveSetByContent` |

An activity without sequencing has the defaults of IMS Simple Sequencing: choice open, flow closed. A value the reader
cannot use — a boolean that is not one, a number out of its range, an unknown timing — is noted and its default kept.
A rule with an unknown action, an action of another timing, an unknown condition or no condition is noted and left out.
An activity may declare at most 32 rules of one timing and 32 rollup rules, 16 conditions per rule, 64 objectives and
16 maps per objective; more refuses the manifest.

### What is read for SCORM 1.2

Each item's `<adlcp:prerequisites>`, of type `aicc_script` or without type, parsed by `Prerequisites::parse()`:

| Writing | Met when |
|---|---|
| `intro` | The item `intro` is passed or completed |
| `a & b`, `a \| b` | Both, either |
| `~a` | `a` is not met |
| `(a \| b) & c` | Parentheses group; `&` binds before `\|` |
| `quiz = "failed"`, `quiz <> passed` | The status compared: `passed`, `completed`, `failed`, `incomplete`, `browsed`, `not attempted`, or their initials `p`, `c`, `f`, `i`, `b`, `n` |
| `2*{a, b, c}` | At least two of the members are met |

An item the tree does not hold reads as not attempted. An expression is 4 096 bytes and 32 levels of nesting at most;
one that cannot be read is noted and left out. A SCORM 1.2 tree opens flow and choice on every cluster, and lets the
content alone set completion and success.

## Running a session

```php
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\Outcome;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingState;

$sequencer = new Sequencer();
$state = SequencingState::begin();

$result = $sequencer->navigate($tree, $state, NavigationRequest::Start);
$state = $result->state;           // always the state to keep
$result->outcome;                  // Outcome::Delivered
$result->activity;                 // 'intro': launch its resource

// The content reports while it runs, from the run-time data:
$state = $sequencer->report($tree, $state, AttemptResult::fromRuntime($data->status(), $sessionTime));

$result = $sequencer->navigate($tree, $state, NavigationRequest::Continue);
$result = $sequencer->navigate($tree, $result->state, NavigationRequest::Choice, 'quiz');
```

| Request | What it does |
|---|---|
| `Start` | Begins a session: flows from the root into its first deliverable leaf. Refused while a session is under way or suspended |
| `ResumeAll` | Delivers the activity a `SuspendAll` suspended, its attempts resumed rather than begun again |
| `Continue`, `Previous` | Ends the current attempt, then flows to the next or the previous leaf. Needs flow on the parent; `Previous` is refused under forward only. `Continue` past the last activity ends the session |
| `Choice` | Ends the current attempt and delivers the activity named — a cluster is entered by flow. Refused where the parent closes choice, where a hidden-from-choice rule applies on the path, where an activity under way may not be left (`choiceExit`), backward under forward only, past a stop-forward-traversal rule, or for a child not drawn in this attempt |
| `Exit` | Ends the current attempt. The outcome is `Waiting` until the learner chooses, unless the termination reaches the root, which ends the session |
| `ExitAll` | Ends every attempt under way and the session |
| `SuspendAll` | Suspends the current activity and its ancestors and ends the session; the next session begins by `ResumeAll` |

| `Outcome` | Meaning |
|---|---|
| `Delivered` | `SequencingResult::$activity` is the leaf to launch |
| `SessionEnded` | The session is over: show the platform's own page |
| `Waiting` | Nothing to launch until the learner makes a choice |
| `Refused` | `SequencingResult::$reason` says why, in one sentence. A refusal that came after the current attempt ended still returns that state |

### What the engine applies

- **Termination.** Ending an attempt on a tracked leaf completes it and satisfies its primary objective when its content
  set neither, unless `completionSetByContent` or `objectiveSetByContent` says the content alone sets them. A leaf whose
  content asked to suspend (`AttemptResult::$suspend`, `cmi.exit` set to `suspend`) stays suspended: its next delivery
  resumes it. Then the exit rules of the ancestors, from the root down; the first that holds ends the attempts below it
  and on it. Then the post-condition rules of the current activity: `exitParent` climbs and ends the parent's attempt,
  `exitAll` ends the session, `retry`, `retryAll`, `continue` and `previous` replace the request. A termination that
  reaches the root ends the session.
- **Pre-condition rules.** `skip` is passed over by flow, in both directions, and stays open to choice; `disabled` stops
  delivery and flow; `hiddenFromChoice` refuses a choice on the activity and below it; `stopForwardTraversal` refuses a
  choice that moves forward past it.
- **Limits.** An activity with its attempts spent is not delivered again. A duration reported past
  `attemptAbsoluteDurationLimit` makes `timeLimitExceeded` true.
- **Delivery.** Each activity on the path from the root to the leaf delivered that is not under way begins a new
  attempt — its completion, objectives and duration cleared —, or resumes when suspended. A new attempt on a cluster
  also clears the objectives of its descendants under `useCurrentAttemptObjectiveInfo`, and their completion under
  `useCurrentAttemptProgressInfo`. The whole tree is one attempt: the root's, from `Start` to the end of the session.
- **Rollup.** After each report and each end of an attempt, from the activity up to the root: the measure of a cluster
  is the mean of the measures of its tracked children weighted by `objectiveMeasureWeight`, over all of them; a primary
  objective satisfied by measure is satisfied at `minNormalizedMeasure` or above, and waits while active when
  `measureSatisfactionIfActive` is false; otherwise the rollup rules decide — not satisfied then satisfied, incomplete
  then completed —, or the defaults: satisfied when all children are, not satisfied when the satisfaction of all is
  known; completed when all are, incomplete when the completion of all is known. A child counts when it is tracked, not
  disabled, its `rollupObjectiveSatisfied` or `rollupProgressCompletion` allows it, and its rollup considerations hold.
  A rollup condition on an unknown status is unknown, and a rule holds only on known answers; a sequencing rule reads
  an unknown status as false, before `not`.
- **Global objectives.** An objective reads the satisfaction and the measure of the first global objective it maps
  with `readSatisfiedStatus` or `readNormalizedMeasure` that knows them, else its own; each change of its own is written
  to the global objectives it maps with `writeSatisfiedStatus` or `writeNormalizedMeasure`.
- **Selection and reordering.** The children of a cluster are drawn and shuffled when the flow or a choice first needs
  them, from the seed of the state, the cluster and its attempt count: the same state gives the same draw. They are kept
  in the state for the session (`once`) or drawn again for the next attempt (`onEachNewAttempt`).
- **SCORM 1.2.** An item whose prerequisites are unmet is passed over by flow and refused to choice. The status of an
  item is the `lesson_status` its content last reported, else what its data reads as — `passed` or `failed` from its
  satisfaction, `completed` or `incomplete` from its completion, `not attempted` —; `Sequencer::lessonStatus()` gives it.

### Reporting results

`report()` records what the content of the delivered activity reported, then rolls it up; it throws
`SequencingRefused` when no attempt is under way.

| `AttemptResult` | Sets |
|---|---|
| `$completed` | Its completion |
| `$passed` | The satisfaction of its primary objective |
| `$scaledScore` | The measure of its primary objective, kept from -1 to 1 |
| `$objectives` | The satisfaction and measure of its other objectives, by identifier, as `ObjectiveStatus`; an identifier it does not declare is left out |
| `$duration` | The duration of its attempt, for `timeLimitExceeded` |
| `$lessonStatus` | The `lesson_status` of SCORM 1.2, for the prerequisites |
| `$suspend` | Whether the attempt is suspended when it ends |

`AttemptResult::fromRuntime()` reads a `Runtime\RuntimeStatus`; `AttemptResult::fromLessonStatus()` reads a
`lesson_status` of SCORM 1.2, `browsed` kept as written. A null value leaves what the state holds.

## The state the platform keeps

`SequencingState` is all the sequencer knows of a learner on a tree. A platform stores it after every request, and
hands it back with the next one:

```php
$stored = json_encode($state->toArray(), JSON_THROW_ON_ERROR);
// …
$state = SequencingState::fromArray(json_decode($stored, true, flags: JSON_THROW_ON_ERROR), $tree);
```

| Key | Holds |
|---|---|
| `version` | `1`, the version of this form |
| `seed` | The seed of the selection and reordering of children, drawn by `begin()` or given to it |
| `current` | The activity delivered or last delivered, null outside a session |
| `suspended` | The activity `SuspendAll` suspended, null otherwise |
| `activities` | By identifier, in the order of the tree, only those something is known of: `attempts`, `active`, `suspended`, `completed` (null while unknown), `duration` in hundredths of a second, `lesson`, `objectives` by key — the identifier, or `#primary` for a primary objective without one — each with `satisfied` and `measure`, and `order`, the children drawn |
| `globals` | The global objectives by identifier, each with `satisfied` and `measure` |

`fromArray()` checks every value and that each identifier is one of the tree, and throws `SequencingRefused` otherwise:
a state stored for another organization, or edited by hand, is refused rather than believed. A state belongs to one
learner and one tree; global objectives are kept per state, so a platform that shares them across courses — as
`objectivesGlobalToSystem` asks — copies them from one state to another itself. Reading `activity()` gives an
`ActivityStatus`, `global()` and `globals()` give `ObjectiveStatus` values. The state reads the same in any worker:
the `Sequencer` and the tree are immutable and keep nothing between two requests.

## What is not supported

- The navigation requests `Abandon`, `AbandonAll` and `Jump`.
- `adlseq:constrainedChoiceConsiderations` (`preventActivation`, `constrainChoice`), `adlseq:objectives` and the maps
  of completion and progress of SCORM 2004 4th edition, and the rollup of completion by measure
  (`completedByMeasure`, `minProgressMeasure`, `progressWeight`).
- `outsideAvailableTimeRange`, which is always false: begin and end times are not read. Attempt and activity durations
  other than `attemptAbsoluteDurationLimit`, and `adlnav:presentation`.
- The attempt limit does not take a child out of its parent's rollup.
- The clearing of the data of descendants under `useCurrentAttemptObjectiveInfo` and `useCurrentAttemptProgressInfo`
  happens when the cluster's new attempt begins, rather than by tracking the attempt each value belongs to.
- A table of contents: a platform lists the activities of the tree and asks the sequencer about a choice; the state
  is immutable, so a `navigate()` whose result is not kept changes nothing.
