# cmi5

The bundle imports cmi5 courses, launches their assignable units as cmi5 asks, and receives their statements through
an xAPI endpoint that checks them against the rules of cmi5.

## Importing a cmi5 course

A ZIP package holding `cmi5.xml` at its root, or a course structure alone whose units have absolute URLs, is imported
with the same command as SCORM:

```sh
php bin/console phil:courses:import var/import/navigation.zip
php bin/console phil:courses:import var/import/cmi5.xml
```

| In the course structure | In the course |
|---|---|
| The course, its title and description in `phil_courses.import.language`, else the first written | The course |
| A block, at any depth | A `Block`: its title, its identifier as its source, its parent block, and its place among the children of that parent, blocks and units counted together |
| A block of the first level | Also a module of the outline; its units, and those of its nested blocks, become its activities, the titles of the nested blocks prefixed |
| A unit of the first level | An activity of a module named after the course, in no block |
| A unit | A `cmi5` activity: its IRI as `au_id` and source, its `moveOn` as its requirement, its `masteryScore`, `launchMethod`, `activityType`, `launchParameters` and `entitlementKey`, and the innermost block that holds it |
| A unit whose `moveOn` is `NotApplicable` | An activity the course does not require, as cmi5 counts it satisfied |
| A unit's URL | A file of the package, served from the content origin, or an absolute address |

Identifiers are unique, `moveOn` and `launchMethod` are values cmi5 defines, a mastery score is a decimal from 0 to 1;
a structure that breaks one of these is refused. The objectives are left out, and the report says so. The entitlement
key is handed to the unit, not checked. The command prints how many modules, blocks, activities and files the course
holds.

`Courses::blocks()` gives the blocks of a course, each parent before the blocks it holds, siblings in their order; the
tree is rebuilt from their parents and positions, and from the `block` of each activity. The import of a package bounds
how many blocks a course holds, by `phil_courses.import.limits.items`.

## Launching a unit

When the learner launches a cmi5 activity, the bundle:

1. abandons the session still open for that learner and unit, if any, and issues its `abandoned` statement;
2. opens a session, whose token holds `phil_courses.cmi5.session_ttl` seconds;
3. issues the `launched` statement, with the launch mode `Normal`, the launch URL, the `moveOn` and the launch
   parameters;
4. sends the learner to the unit's URL, with the launch parameters of cmi5:

| Parameter | Value |
|---|---|
| `endpoint` | The xAPI endpoint of the bundle, ending with a slash |
| `fetch` | The fetch URL of the session |
| `registration` | The registration of the enrollment |
| `activityId` | The activity's IRI on this platform: the home page, then `/courses/activities/` and the activity's identifier |
| `actor` | The learner as an xAPI agent: an account whose home page is `phil_courses.cmi5.actor_home_page` and whose name is the learner's stable identifier |

A unit that asks for `OwnWindow` opens in its own window; the others are framed in the activity page. The unit's IRI
in the course structure is the publisher identifier, in the `grouping` of the context template.

## The fetch URL

The unit posts to the fetch URL and receives `{"auth-token": "…"}` once. A second request answers the error `1`; an
unknown, ended or expired session the error `2`; a request that is not a `POST` the error `3`. The token is the
session's identifier and an HMAC of it under `phil_courses.cmi5.secret`: nothing secret is kept in the database. The
unit sends it as HTTP Basic credentials with each xAPI request.

## The xAPI endpoint

| Resource | Methods | What the bundle does |
|---|---|---|
| `statements` | `POST`, `PUT` with `statementId` | Checks each statement, keeps it through the learning record store, applies it; `GET` is refused |
| `activities/state` with `stateId=LMS.LaunchData` | `GET` | The launch data: the context template, the launch mode, the `moveOn`, the mastery score, the launch parameters, the entitlement key; writing it is refused |
| `activities/state` with another `stateId` | `GET`, `PUT`, `POST`, `DELETE` | The unit's own state documents, 64 KiB each, through the learning record store |
| `agents/profile` | `GET` | Not found: the platform keeps no learner preferences; writing one is refused |
| `about` | `GET` | `{"version": ["1.0.3"]}` |

The sessions of [xAPI activities](xapi-activities.md) talk to the same endpoint: their statements pass the checks of
xAPI activities instead of the rules of cmi5, and their state documents belong to the activity identifier of their
package; the launch data is cmi5's only.

Every request but `about` carries the token of an open session and the header `X-Experience-API-Version: 1.0.x`. A
state request names the activity, agent and registration of its session. Cross-origin requests are answered for the
content origin and `phil_courses.cmi5.allowed_origins`, never for any origin.

## The rules of cmi5

A statement is accepted when it keeps these rules, and refused with `403` and the rule it broke otherwise:

- its actor is the agent of the launch, by its account only; its registration and its `sessionid` extension are those
  of the launch; an `id`, when given, is a UUID;
- a statement whose verb cmi5 defines has the activity's IRI as its object and the `cmi5` category;
- `launched`, `abandoned`, `waived` and `satisfied` are the platform's to issue;
- `initialized` comes first and once; nothing follows `terminated`; a statement of another verb comes between them;
- `completed`, `passed` and `failed` carry the `moveon` category and a duration in ISO 8601, as `terminated` does;
- `completed` sets its completion to true, once per registration;
- `passed` and `failed` set their success; nothing of them follows a `passed`; with a mastery score, they give a scaled
  score at least, or below, that score;
- a scaled score is a number from -1 to 1.

A request holds 50 statements and 1 MiB at most. Its body — its size, its JSON, its number of statements, each an
object — is checked whole first. Its statements are then checked, kept and applied one after the other, each against
the state the ones before it left: a statement refused stops the request with `403`, and the statements before it stay
kept and applied.

## Applying the statements

`initialized` opens the session and `terminated` ends it. `completed`, `passed` and `failed` move the learner's
progress, which the requirement of the activity — its `moveOn` — then satisfies. When a teacher waives a cmi5
activity, the platform issues its `waived` statement, in a session of its own.

## The satisfied statements

Each time a unit is satisfied — by a statement, or by a waiver —, the platform issues a `satisfied` statement for each
block the learner has satisfied and has no statement of yet, then for the course:

- a block is satisfied when every required activity it holds, in it or in its nested blocks, is satisfied; a waived
  unit counts as satisfied, and a unit that is not required — a `moveOn` of `NotApplicable` — is left out; a block
  that holds no activity is never satisfied;
- the deepest blocks come first: a nested block before the block that holds it;
- the course is satisfied when every required activity of the course is;
- the object of a block's statement is the identifier its course structure gives it; that of the course, the
  identifier of its course structure, or the platform's IRI of the course — the home page, then `/courses/` and its
  identifier — for a course that has none; the course is the `grouping` of both, with the `moveon` category;
- the statement belongs to the session that satisfied the unit — the session of the waiver for a waiver —, with its
  registration and the learner as actor.

Each statement is issued once for an enrollment and a block, or the course: the satisfaction is noted in
`phil_course_satisfaction` before the statement is kept, so two requests at once issue it once. When the learning
record store refuses the statement, the note is removed and the statement is issued again by the next unit satisfied.
Each block's statement raises `BlockSatisfied`, with the enrollment, the block and the statement's identifiers.

The satisfaction is checked again at each outcome of a unit — `completed`, `passed` or `failed`, of a required unit or
not — and at each waiver. An enrollment whose units are all satisfied but which has no note in
`phil_course_satisfaction` — its statement refused by the store, or its tables brought up to date by
`CourseTables::upgrade()` after its units were satisfied — receives the statements of its blocks and of its course at
its next outcome, dated when they are issued. An enrollment that completed its course under a schema without
`phil_course_satisfaction` had a statement of the course issued then, named by the platform's IRI: it receives a
second one, named by the course structure.

### The cmi5 rules these behaviours follow

| Behaviour | The cmi5 rule |
|---|---|
| A unit whose `moveOn` is `NotApplicable` is imported as an activity the course does not require, and no statement is issued for it | The `moveOn` of an assignable unit in the course structure: `NotApplicable` means the unit is considered satisfied, with no statement from it. Not requiring it leaves the blocks and the course to the other units |
| The course is satisfied once every required unit is satisfied, a waived unit counted, and its statement is issued after a waiver as after a statement | The `satisfied` verb: the platform issues it for a block or a course once every unit in it has met its `moveOn` criteria. A waiver is the platform's way to meet them: the `waived` verb |
| The object of the course's statement is the identifier of its course structure; that of a block, the block's identifier in the structure | The `satisfied` verb: its object is the block or the course as an activity, identified by the `id` the course structure gives it. The platform's IRI of the course names only a course that no cmi5 course structure describes |

A `satisfied` statement issued late, for a course whose units were satisfied before, still follows these rules: the
course is satisfied, and the statement says when the platform recorded it.

## The learning record store

The statements and state documents go through the `LearningRecordStore` port. The bundle has checked every statement
before the store receives it. Three stores plug into it:

| Store | How | What it does |
|---|---|---|
| The store inside the bundle | The default | Keeps the statements and the state documents through DBAL; a statement sent twice with the same identifier is kept once |
| A remote xAPI store | `phil_courses.cmi5.remote_lrs`, with phil/safe-http | Sends the statements to the remote store; the state documents stay in the store inside the bundle |
| The application's own | Its service named in `phil_courses.cmi5.lrs` | Whatever the adapter does; see [Extension points](extension-points.md) |

### A remote xAPI store

```yaml
phil_courses:
    cmi5:
        remote_lrs:
            enabled: true
            endpoint: 'https://lrs.example.org/xapi/'
            username: '%env(LRS_KEY)%'
            password: '%env(LRS_SECRET)%'
```

`HttpLearningRecordStore` sends each statement with `PUT` to `<endpoint>/statements?statementId=<id>`, as JSON, with
the `X-Experience-API-Version` header of `version` and HTTP Basic authentication when a user name and a password are
given. It goes through a `SafeHttpClient` of its own, reset between requests:

- the endpoint is an `https` address of a public host, without a query or a fragment; a host that is or resolves to a
  loopback, private, link-local or reserved address is refused, as is any redirect;
- the store may stay silent `timeout` seconds, and one statement takes `max_duration` seconds at most;
- an answer `2xx` keeps the statement; `409 Conflict` means the store has it already, and counts as kept;
- any other answer, or a store out of reach, throws `RecordStoreUnavailable`, which names the status only: the xAPI
  endpoint answers the unit `503`, so that it sends its statements again, and the launch page says the launch could not
  be recorded;
- the log, when the application has a `logger` service, names the statement's identifier, the status and the kind of
  failure; never the statement, the registration, the learner, the credentials nor what the store answered.

### The state documents stay local

With a remote store, the state documents never leave the application: `HttpLearningRecordStore` reads, writes and
deletes them in the store inside the bundle, on DBAL, which it is given as `DbalLearningRecordStore`. They are the
runtime data of the launches — what a unit keeps from one launch to the next —, read back by the units through the
bundle's own endpoint, never by the remote store's readers. The State resource of xAPI names its document by an
agent, which the `LearningRecordStore` port does not give: an application that needs the state documents in the remote
store writes its own adapter of the port, named in `phil_courses.cmi5.lrs`.

### The limits of the remote store

- **Private networks.** phil/safe-http refuses a host that is or resolves to a loopback, private, link-local or
  reserved address. A learning record store on the application's own network is out of reach of the remote store: an
  application that runs one there writes its own adapter of the port.
- **Requests of several statements.** A statement is kept by the remote store before the bundle applies it, one after
  the other. When the store does not keep one, the request ends with `503`, and the statements before it stay kept and
  applied. When the unit sends the whole request again, the rules of cmi5 may refuse it with `403` — an `initialized`
  sent twice, say —: the statements kept are not lost, but the unit has to send the rest alone.
- **Erasure.** A statement the remote store keeps cannot be erased by the platform: see [Privacy](#privacy).

## Privacy

With phil/privacy-bundle installed, `Cmi5Privacy` gives the cmi5 records of a learner in an access request and erases
them in an erasure request, under the module `courses_cmi5`. The learner is the stable identifier the courses know them
by; their records are those of the registrations of their enrollments, the launch sessions and statements of their
[xAPI activities](xapi-activities.md) included.

| Record | Access request | Erasure request |
|---|---|---|
| The registrations: enrollment, course, registration | Exported | Kept with the enrollment |
| The launch sessions: activity, state, launch and end | Exported | Deleted |
| The statements of the store inside the bundle | Exported, each as the JSON it was sent as, with when it was stored | Deleted |
| The state documents | Exported, by registration, state identifier and the SHA-256 of the activity's IRI, the document as its JSON | Deleted |
| The notes of the `satisfied` statements issued, by block or course | Exported | Deleted, unless the statements are kept outside the bundle |

The erasure runs again without harm. Its report names each table, and the export is empty for a learner without cmi5
records.

### What a store outside the bundle keeps

A store outside the bundle keeps what it was sent, and the platform cannot erase it there:

- **A remote xAPI store**, `phil_courses.cmi5.remote_lrs`, keeps the statements. xAPI gives no way to erase a
  statement: a statement can only be voided by another, which keeps both. The state documents stay in the store
  inside the bundle, and are erased.
- **The application's own store**, `phil_courses.cmi5.lrs`, keeps the statements and the state documents, which the
  bundle neither reads nor erases.

The export then says so under `kept_elsewhere`: the records the store keeps, the store — the endpoint of the remote
store, without its credentials, or the class of the application's adapter —, the registrations, by which the statements
are found there, and a note. The erasure report names that store `learning_record_store`, with the effect `survives`.
The notes of the `satisfied` statements survive as well, since the statements they stand for are still in that store: a
`satisfied` statement is never sent twice. The person, or the site, asks the operator of that store, with the
registrations of the export.
