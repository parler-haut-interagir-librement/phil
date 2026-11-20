# Extension points

The bundle is in its `0.x` releases: the ports, the events and the services below may change in a minor release, said
in the changelog. They are meant to be stable from `1.0`.

## `ActivityType`

A type of activity, in `Phil\CoursesBundle\Application\Contract`: the name an activity keeps, the settings it checks,
whether the learner marks it done, and where the learner goes to take it. A service implementing it is collected with
the tag `phil_courses.activity_type`, added by autoconfiguration. The bundle has `page`, `link`, `scorm`, `cmi5` and
`xapi`.

```php
final readonly class WorkshopActivity implements ActivityType
{
    public function __construct(private UrlGeneratorInterface $urls) {}

    public function name(): string
    {
        return 'workshop';
    }

    public function settings(array $settings): array
    {
        return ['room' => (string) ($settings['room'] ?? '')];
    }

    public function completesManually(): bool
    {
        return true;
    }

    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        return Launch::newWindow($this->urls->generate('app_workshop', ['room' => $activity->stringSetting('room')], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
```

A type whose content reports its own outcome answers `false` to `completesManually()`, and calls
`Enrollments::record()` when the outcome comes.

## `CurrentLearner`

Who takes the courses in the request, or null. `SecurityCurrentLearner` reads Symfony Security's user identifier and
refuses one that holds an `@`; an application whose users sign in with their e-mail address names its own service in
`phil_courses.current_learner`, returning a `LearnerId` of a stable identifier.

## `ContentStorage`

Where the files of the imported packages are kept: `write()`, `exists()`, `read()` and `removeDirectory()`, on
relative paths the import checked. `FilesystemContentStorage` writes to `phil_courses.storage.directory`; an
application names its own in `phil_courses.storage.service`, an object storage for instance. The content origin then
serves the files from there.

## `LearningRecordStore`

Where the cmi5 statements and state documents are kept: `store()`, `state()`, `saveState()` and `deleteState()`.
`DbalLearningRecordStore` keeps them through DBAL. `HttpLearningRecordStore`, enabled by `phil_courses.cmi5.remote_lrs`,
sends the statements to a remote xAPI store and keeps the state documents local, in `DbalLearningRecordStore`: the
port names no agent, which the State resource of xAPI asks for. An adapter of
the application, named in `phil_courses.cmi5.lrs`, keeps them elsewhere; it receives statements the bundle has
already checked against the rules of cmi5, with their `id` set. An adapter that cannot keep a statement throws
`RecordStoreUnavailable`: the xAPI endpoint answers `503`, and a `satisfied` statement it refused is issued again
later.

The privacy module reads and erases the statements and state documents of a learner's registrations in
`DbalLearningRecordStore`, with `statementsOf()`, `statesOf()` and `erase()`. With `HttpLearningRecordStore`, whose
`endpoint` names the remote store, or an adapter of the application, an access request says that store keeps them, and
an erasure reports it as surviving: see [cmi5](cmi5.md#privacy).

## `XapiPackageReader`

Reads the `tincan.xml` of an xAPI package into an `XapiPackage` — the course's IRI, title and description, its
`XapiLaunchable` activities and the notes of the reading —, or throws `PackageRefused`. The import asks the service
named in `phil_courses.import.xapi_reader` for an archive that holds `tincan.xml` and neither `cmi5.xml` nor
`imsmanifest.xml`. It is the narrow place where a library's reader of xAPI packages plugs in, through an adapter
that bounds the reading; without one, xAPI packages are refused. See [xAPI activities](xapi-activities.md).

## `AssigneeMembers`

The members of a group or a unit of an organization a course is assigned to: `supports(AssigneeKind $kind)` and
`members(AssigneeKind $kind, string $assignee)`, the stable identifiers of the learners, read lazily. Autoconfigured
with the tag `phil_courses.assignee_members`; the first service that supports a kind answers. See
[mandatory training](mandatory-training.md#groups-and-units).

## `TrainingNotifier`

Tells a person about an assigned course: `notify(TrainingNotice $notice, string $recipient, Obligation $obligation)`.
`NotificationsTrainingNotifier` sends a notification of phil/notifications-bundle, identifiers only, when that bundle
is registered; an application names its own in `phil_courses.training.notifier`.

## `AssignmentRepository` and `TranscriptRepository`

The stores of the assignments and obligations, and of the transcripts and credits, aliased by their ports to
`DbalAssignmentRepository` and `DbalTranscriptRepository`. `AssignmentRepository::actionable()` reads the obligations a
run acts on a page at a time, after the key of the last one of the page before; `removeOf()` on both serves the
privacy module.

## `PackageOpener` and `PackageArchive`

Opening a package for reading: the contracts of phil/elearning-packages. `ZipPackageOpener` reads ZIP archives through
PHP's zip extension within the limits of the import; an application that receives packages in another form aliases
`Phil\ElearningPackages\Contract\PackageOpener` to its own service, whose archive keeps the same checks.

`Phil\ElearningPackages\PackageReader` is a service with the limits and the language of `phil_courses.import`: an
upload form checks a package with it before the import.

## `Completions` and `CourseCompletions`

`Completions::completion(CourseId $course, LearnerId $learner)` answers whether a learner completed a course: a
`Completion` — the enrollment, the course, the learner and the moment, in UTC — or null. It follows the rule that raises
`CourseCompleted`, and a completion stays once reached. Another part of the application that keeps its own model —
certificates, badges, a report — asks it rather than reading the stores.

With phil/certificates-bundle 0.2 or later, which declares the `CourseCompletions` port, the bundle registers
`CertificatesCourseCompletions` and aliases the port to it. phil/certificates-bundle then asks it for the completion of
a course, by the course's identifier in this bundle and the learner's identifier, and receives the identifier of the
enrollment that completed it, or null. An identifier this bundle cannot hold names no completion.

## `Cmi5Endpoints`

The absolute URLs of the xAPI endpoint and of a session's fetch URL. `RoutedCmi5Endpoints` generates them from the
routes of `config/cmi5_routes.php`.

## `CourseRepository`, `EnrollmentRepository` and `LaunchSessionRepository`

The stores of the courses and of the enrollments, aliased by their ports to the DBAL stores. `CourseRepository` keeps
the blocks of a cmi5 course with `saveBlock()` and `blocks()`; `LaunchSessionRepository` gives and removes the cmi5
launch sessions of enrollments with `ofEnrollments()` and `removeOfEnrollments()`, for the privacy module;
`EnrollmentRepository` notes the `satisfied`
statements an enrollment has with `satisfactions()`, `claimSatisfaction()` — which says false when the satisfaction
was noted already, so that two requests at once issue one statement — and `releaseSatisfaction()`, and forgets the
progress of an enrollment that starts again with `removeProgresses()`. An application that replaces a store implements
them.

## `ScormAttemptRepository`

Where the SCORM attempts and their tracking data are kept: `save()`, `find()`, `latest()` — the learner's last attempt at
an activity —, `ofEnrollments()` and `removeOfEnrollments()`, which the privacy module reads and erases through.
`DbalScormAttemptRepository` keeps them through DBAL, with the seconds the sessions of each attempt ran by the server's
clock; an application that keeps them elsewhere aliases the port to its own service, and keeps `elapsed` and
`sessionOpenedAt`, which the time limits are counted from.

## Events

The events of [the course model](course-model.md#events) are dispatched through the PSR-14 dispatcher, and carry
identifiers only: a listener reads the rest from the stores. A listener sends the reminder of a release, awards a
certificate on `CourseCompleted`, tells a teacher of a `RetryRequested`, or follows a learner's path through the
blocks of a cmi5 course on `BlockSatisfied`. `ScormSessionEnded` is dispatched at the end of each session of a SCORM
activity, with whether its attempt ended. The mandatory training dispatches `TrainingAssigned`, `ObligationOverdue`,
`ObligationRenewed`, `EnrollmentRenewed` and `CreditsGranted`; see [mandatory training](mandatory-training.md#events).

## Services

Every entry point is a public service named by its class: `Courses`, `Enrollments`, `Availabilities`, `Learning`,
`Completions`, `SecondChances`, `CourseImporter`, `ResultImporter`, `Cmi5Statements`, `Cmi5Tokens`, `Cmi5Launcher`,
`ActivityTypes`, `ScormRuntime`, `ScormTokens`, `XapiLauncher`, `XapiStatements`, `Assignments`, `Transcripts`,
`TrainingRun`, and the controllers of the pages, `SecondChancesController`, `ScormController`, `ScormFrameController`
and `TrainingController` among them.
The stores are aliased by their ports: `CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`,
`LaunchSessionRepository`, `ScormAttemptRepository`, `AssignmentRepository`, `TranscriptRepository`.
