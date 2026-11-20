# phil/courses-bundle

**Online courses for Symfony: SCORM, cmi5 and xAPI content run inside the platform, mandatory training, transcripts.**

phil/courses-bundle gives an application online courses. A course holds modules in order, and each module holds
activities: a page of text, a link, the content of an imported SCORM package, a cmi5 assignable unit, or an activity of
an xAPI package. Activity types
are a port with a few adapters, so that an application adds its own. A learner enrolls once in a course; each activity
records what they did — completed, passed, failed, waived, the best score — and is satisfied by the requirement it
carries, the values of cmi5's `moveOn`. The course is completed when every required activity is satisfied.

A module or an activity opens at once, on a date, or a number of days after the learner enrolled, counted from
midnight in the site's time zone. A course may impose the order of its activities. No state is a dead end: a learner
who failed every try of an activity asks for a second chance, which a teacher grants, refuses, or answers by waiving
the activity, with a note, on an administration page or from the console; with phil/audit-bundle, each step is
written to the audit trail.

The bundle imports SCORM 1.2, SCORM 2004 and cmi5 packages, to run them or to migrate courses from another platform,
read by phil/elearning-packages. The ZIP archive is read within bounds — its size, its number of entries, the size of
each file uncompressed, its compression ratio —, and an entry that would leave the package, a symbolic link or a corrupt
entry is refused. The manifest is parsed without any document type or entity. The files are stored apart, and served
from a content origin that is not the application's, with a content security policy.
SCORM content runs inside the platform: a Stimulus controller frames it under the run-time API of SCORM 1.2 (`API`) or
SCORM 2004 (`API_1484_11`), which answers each call at once by the data model of phil/elearning-packages and sends the
commits to an endpoint that checks them again. Attempts are kept with their tracking data — suspended and resumed,
attempted again, reviewed read-only once ended —, and feed the learner's progress, the mastery score of the manifest
included; the time allowed, the manifest's or the activity's own, is enforced by the server's clock.
The tree of the blocks of a cmi5 course structure is kept: nested blocks, their order and their identifiers.
cmi5 units are launched with the parameters of cmi5, fetch their token once, and send their statements to an xAPI
endpoint of the bundle, which checks them against the rules of cmi5 before a learning record store keeps them. The
platform issues the `satisfied` statement of each block once its required units are satisfied, then of the course,
once for each enrollment. The store inside the bundle keeps the statements through DBAL; the bundle also sends them to
a remote xAPI learning record store through the guarded client of phil/safe-http, the state documents of the units
staying in the application's database, and an application's own store plugs into the same port.

An xAPI package runs as an activity of its own: launched with the parameters xAPI packages read and a token of its
session, it talks to the same xAPI endpoint, its statements checked — the learner of the session, the registration of
the enrollment, no voiding — and its `completed`, `passed` and `failed` statements move the learner's progress, with a
mastery score when it has one. Its package is imported through a reader of `tincan.xml` the application configures.

A course can be mandatory: assigned to a learner, to the members of a group or of a unit, by a due moment, each member
enrolled and reminded through phil/notifications-bundle before, on and after the due moment, the person who assigned it
told once it is overdue; the learner and the managers see the overdue state on their pages. A recertification renews a
completion after a number of months, read by phil/recurrence, and the learner takes the course again. A course grants
credits — continuing education units or hours — on its completion, and each completion enters the learner's
transcript, a page, JSON and CSV. Symfony Scheduler runs the reminders every day.

Another part of the application — certificates, badges, a report — asks `Completions` whether a learner completed a
course, and keeps its own model; with phil/certificates-bundle, the bundle answers its `CourseCompletions` port.

The services keep no state between requests and are safe in a long-running worker. The stores use DBAL, and are tested
on SQLite, MySQL 8.4 and PostgreSQL 17.

## Status

| Part | State |
|---|---|
| Courses, modules and activities through DBAL; activity types as a port with the page, link, SCORM, cmi5 and xAPI adapters | Implemented and tested |
| Enrollment, progress by activity, the requirement of each activity, completion by course, events of PSR-14 | Implemented and tested |
| `Completions`, and the adapter of the `CourseCompletions` port of phil/certificates-bundle 0.2 | Implemented and tested |
| Release on a date or days after the enrollment in the site's time zone; imposed order | Implemented and tested |
| Second chances asked for, granted, refused or answered by a waiver with a note, the trail of phil/audit-bundle | Implemented and tested |
| The administration page of the second chances, behind a configurable role, with the recipes of the kit, English and French | Implemented and tested |
| The import of SCORM 1.2 and 2004 packages through phil/elearning-packages, sequencing kept for information, results imported from a CSV export | Implemented and tested |
| The SCORM run time: the API of SCORM 1.2 and 2004 in the browser, every element of their data models checked in the browser and on the server, attempts suspended, resumed and attempted again, tracking data kept, progress recorded, navigation requests followed | Implemented, tested on SQLite, MySQL and PostgreSQL, and fuzzed |
| The review of an ended SCORM attempt, read-only, and the time limits of the manifest and of the activity, enforced on the server | Implemented, tested on SQLite, MySQL and PostgreSQL |
| The tracking data of the SCORM attempts and the cmi5 records of a learner — launch sessions, statements, state documents — in the access and erasure requests of phil/privacy-bundle; what a remote learning record store keeps said in the export | Implemented and tested |
| The sequencing and navigation of SCORM 2004 beyond the flow and the choice of the outline: rules, rollup, shared objectives | Not yet: see [SCORM run time](doc/scorm-runtime.md#what-the-bundle-does-not-do-yet) |
| The import of cmi5 packages and course structures, through phil/elearning-packages, the tree of the blocks kept, a unit whose `moveOn` is `NotApplicable` not required | Implemented and tested |
| cmi5 launches, the fetch URL, the xAPI endpoint with the rules of cmi5, the learning record store as a port | Implemented and tested |
| The `satisfied` statements of the blocks, innermost first, and of the course, once for each enrollment, named by the course structure, with the `BlockSatisfied` event | Implemented and tested |
| The remote xAPI learning record store through phil/safe-http, for the statements | Implemented and tested; its integration test runs against a store the environment names |
| xAPI activities: the launch parameters of xAPI packages, the token of the session, the statements checked and kept, completion, success and score from `completed`, `passed` and `failed`, a mastery score, their records in the privacy module of cmi5 | Implemented and tested |
| The import of xAPI packages, through the `XapiPackageReader` port | Implemented and tested; the adapter of a reader of `tincan.xml` is the application's until phil/elearning-packages reads them |
| Mandatory training: courses assigned to a learner, a group or a unit by a due moment, reminders before, on and after it through phil/notifications-bundle and Symfony Scheduler, the overdue state on the learner's and the managers' pages | Implemented and tested |
| Recertification through phil/recurrence: a completion renewed after a number of months, the enrollment starting again | Implemented and tested |
| Credits in continuing education units or hours, the transcript as a page, JSON and CSV, its privacy module | Implemented and tested |
| The state documents of the units with a remote store | Kept in the store inside the bundle, never sent: the port names no agent, which the State resource of xAPI asks for |
| A remote store on a private network | Refused by phil/safe-http: an application's own adapter of the port reaches it |
| The upgrade of the tables of an earlier schema, on SQLite, MySQL and PostgreSQL | Implemented and tested |
| The content served from its own origin, with its content security policy | Implemented and tested |
| `phil:courses:import`, `phil:courses:import-results`, `phil:courses:retries` | Implemented and tested |
| The pages — outline, activity, enrollment, second chance — with the recipes of the kit, English and French | Implemented and tested |
| The Flex recipe: the bundle, its configuration and its routes | Written in `recipe/0.2`, for the recipes repository of PHIL; `recipe/0.1` serves the versions 0.1 |
| Branching lessons through phil/story-graph, quizzes through phil/assessment, access through phil/content-access | Planned, as activity types and an access port, once those libraries are written |
| LTI 1.3 activities, hosted video with consented watch tracking | Planned, as activity types the LTI and video bundles add |
| Cohorts with live meetings, courses sold through the catalogue, dependencies between courses, learning paths, catalogues by unit | Planned |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the tables and their upgrade, the routes, the content origin, the recipes of the kit, the recipe. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [The course model](doc/course-model.md) | Courses, modules, blocks, activities, enrollments, progress, releases, order and second chances, with their administration page. |
| [SCORM import](doc/scorm-import.md) | What a SCORM package becomes, what is kept for information, what is left out, and imported results. |
| [SCORM run time](doc/scorm-runtime.md) | How a SCO runs: the API in the browser, the commit endpoint, attempts, progress, packages of several SCOs, what is supported and what is not yet, privacy. |
| [cmi5](doc/cmi5.md) | The import of cmi5 packages and their blocks, the launch, the fetch URL, the xAPI endpoint, the `satisfied` statements and the learning record stores, inside the bundle or remote. |
| [xAPI activities](doc/xapi-activities.md) | xAPI packages as activities: the launch, the statements, the outcome they record, the import through a reader, privacy. |
| [Mandatory training](doc/mandatory-training.md) | Courses assigned by a due moment, groups and units, the states, the reminders, the run and its schedule, recertification. |
| [Credits and transcripts](doc/credits-and-transcripts.md) | The credits of the courses, the transcript as a page, JSON and CSV, and its privacy. |
| [Security](doc/security.md) | The bounds of the archive and the manifest, the content origin and its headers, the SCORM run time, the remote store, the administration page. |
| [Extension points](doc/extension-points.md) | The ports, the events, the services to replace, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers and the learning record store its integration tests read, and the fuzz target. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```sh
composer require phil/courses-bundle
php bin/console ux:install button badge progress textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_courses.yaml
phil_courses:
    timezone: America/Toronto
    content:
        base_url: 'https://content.example.org'
        application_origin: 'https://www.example.org'
    cmi5:
        actor_home_page: 'https://www.example.org'
```

```sh
php bin/console phil:courses:import var/import/safety-course.zip
```

## Requirements

PHP 8.5 or later with the `mbstring` and `sodium` extensions, and those phil/elearning-packages reads packages with —
`dom`, `libxml` and `zip` —, Symfony 8.2 or later, and Doctrine DBAL 4.5. The remote learning record store needs
phil/safe-http, the administration pages Symfony Security, the SCORM run time symfony/stimulus-bundle with
AssetMapper, the reminders of the mandatory training phil/notifications-bundle, and their schedule symfony/scheduler
with symfony/messenger.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
