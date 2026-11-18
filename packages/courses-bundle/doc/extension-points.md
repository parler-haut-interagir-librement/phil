# Extension points

The bundle is in its `0.x` releases: the ports, the events and the services below may change in a minor release, said
in the changelog. They are meant to be stable from `1.0`.

## `ActivityType`

A type of activity, in `Phil\CoursesBundle\Application\Contract`: the name an activity keeps, the settings it checks,
whether the learner marks it done, and where the learner goes to take it. A service implementing it is collected with
the tag `phil_courses.activity_type`, added by autoconfiguration. The bundle has `page`, `link`, `scorm` and `cmi5`.

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

## `CourseRepository` and `EnrollmentRepository`

The stores of the courses and of the enrollments, aliased by their ports to the DBAL stores. `CourseRepository` keeps
the blocks of a cmi5 course with `saveBlock()` and `blocks()`; `EnrollmentRepository` notes the `satisfied`
statements an enrollment has with `satisfactions()`, `claimSatisfaction()` — which says false when the satisfaction
was noted already, so that two requests at once issue one statement — and `releaseSatisfaction()`. An application that
replaces a store implements them.

## `ScormAttemptRepository`

Where the SCORM attempts and their tracking data are kept: `save()`, `find()`, `latest()` — the learner's last attempt at
an activity —, `ofEnrollments()` and `removeOfEnrollments()`, which the privacy module reads and erases through.
`DbalScormAttemptRepository` keeps them through DBAL; an application that keeps them elsewhere aliases the port to its
own service.

## Events

The events of [the course model](course-model.md#events) are dispatched through the PSR-14 dispatcher, and carry
identifiers only: a listener reads the rest from the stores. A listener sends the reminder of a release, awards a
certificate on `CourseCompleted`, tells a teacher of a `RetryRequested`, or follows a learner's path through the
blocks of a cmi5 course on `BlockSatisfied`. `ScormSessionEnded` is dispatched at the end of each session of a SCORM
activity, with whether its attempt ended.

## Services

Every entry point is a public service named by its class: `Courses`, `Enrollments`, `Availabilities`, `Learning`,
`Completions`, `SecondChances`, `CourseImporter`, `ResultImporter`, `Cmi5Statements`, `Cmi5Tokens`, `Cmi5Launcher`,
`ActivityTypes`, `ScormRuntime`, `ScormTokens`, and the controllers of the pages, `SecondChancesController`,
`ScormController` and `ScormFrameController` among them.
The stores are aliased by their ports: `CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`,
`LaunchSessionRepository`, `ScormAttemptRepository`.
