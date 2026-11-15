# Extension points

## The repositories

`Phil\CertificatesBundle\Domain\Contract\CourseRepository`, `CertificateRepository` and `LearningActivityRepository` are
where courses, certificates and activities of continuing education are kept. With DoctrineBundle, the bundle aliases
them to its Doctrine repositories, whose `save()` flushes. Without it, the application registers services of its own
under these interfaces:

```php
interface CertificateRepository
{
    public function save(Certificate $certificate): void;
    public function findByCode(string $code): ?Certificate;
    public function nextStatusIndex(): int;
    public function revokedIndexes(int $from, int $to): array;
    public function listed(Course $course, int $limit, int $offset = 0): array;
    public function countListed(Course $course): int;
    public function findRenewalOf(Certificate $certificate): ?Certificate;
    public function findByCompletion(Course $course, string $completion): ?Certificate;
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array;
}

interface LearningActivityRepository
{
    public function save(LearningActivity $activity): void;
    public function remove(LearningActivity $activity): void;
    public function find(string $id): ?LearningActivity;
    public function findBySource(MemberId $member, string $source): ?LearningActivity;
    public function of(MemberId $member, Period $period, int $limit, int $offset = 0): array;
    public function total(MemberId $member, Period $period, ?Course $toward = null): Credits;
}
```

`expiring()` gives the certificates neither revoked nor renewed that end after `$after` and by `$until`, the first to
end first, then by identifier; `total()` sums in the storage, so that a member's credits are never loaded to be added
up. Without a `LearningActivityRepository`, a recertification that asks for credits is refused.

`findByCompletion()` gives the certificate of a course awarded on that completion; the storage keeps a completion to one
certificate of a course.

`nextStatusIndex()` answers one past the highest index given, and the storage keeps the indexes and the codes unique,
so that two awards at once cannot share one: the second is refused rather than share a status bit. `Awarding` asks for
the index and saves the certificate under the lock of `lock_factory` when there is one, so a repository needs no lock of
its own. When the storage refuses a certificate whose index, code or completion of the course another holds, `save()` throws
`Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken`, once the storage is ready for another attempt, and
`Awarding` tries again with the next index ([Courses and awarding](courses-and-awarding.md#two-awards-at-once)). The
Doctrine repository does so with the `doctrine` registry, whose manager it resets first.

## The source of completions

`Phil\CertificatesBundle\Domain\Contract\CourseCompletions` tells whether a member completed a course of another
system, by the key the certificate course names as its completion source and the member's stable identifier:

```php
interface CourseCompletions
{
    /** opaque non-empty identifier of the completion, or null when not completed */
    public function completion(string $course, string $holder): ?string;
}
```

The bundle registers none. When the application aliases the interface to a service of its own, `Awarding` asks it for
the courses that name a completion source and keeps the identifier it answers on the certificate; without one, those
courses award nothing. [Completion sources](completion-sources.md) describes the port, the awards on completion and a
listener of the application's own completion event.

## The URLs

`Phil\CertificatesBundle\Application\Contract\CertificateUrls` gives the absolute URLs a certificate points to: its
course's page, which identifies the credential's achievement, its own page, which the QR code opens, and its status
entry. The bundle's `RoutedCertificateUrls` builds them from the routes; an application that serves the register
elsewhere — another host, its own pages — aliases the interface to a service of its own.

## The layout and the templates

`register.layout` names the Twig template the pages extend, with the blocks `title` and `content`. The pages
themselves are overridden like any bundle template, under `templates/bundles/PhilCertificatesBundle/`.

## The translations

The pages and the PDF read the translation domain `phil_certificates`. An application adds a language, or changes a
text, in its own `translations/phil_certificates.<locale>.*` file.

## Other status lists

`status_lists.others` names the application's own `StatusLists`, asked for the lists whose name is not of the layout,
for an application that publishes the status of other badges beside its certificates.

## The events

| Event | When | Carries |
|---|---|---|
| `Phil\CertificatesBundle\Domain\Event\CertificateAwarded` | A certificate is awarded and stored | `certificateId`, `code`, `course` |
| `Phil\CertificatesBundle\Domain\Event\CertificateRevoked` | A certificate is revoked and stored so | `certificateId`, `code`, `course` |
| `Phil\CertificatesBundle\Domain\Event\CertificateRenewed` | A recertification's certificate is stored, after its `CertificateAwarded` | `certificateId`, `code`, `course`, `renewedCertificateId` |
| `Phil\CertificatesBundle\Domain\Event\CertificateExpiring` | A certificate reaches a threshold of `reminders.days` | `certificateId`, `code`, `course`, `daysLeft` |
| `Phil\CertificatesBundle\Domain\Event\LearningActivityRecorded` | An activity of continuing education is stored | `activityId`, `member` |

They go through the PSR-14 dispatcher of the application, `event_dispatcher`, when there is one; `ExpiryReminders`
needs it. All carry identifiers only: a listener reads the rest from the repository, and a message it queues carries
the identifiers too.

## The print subject

Each print of `CertificatePdf` names a `PrintSubject` of the type `certificate` — `CertificatePdf::PRINT_SUBJECT` — and
the certificate's identifier, through `phil/paged-media-bundle`'s `PrintStarting`. A certificate is no licensed work:
an application that checks the licenses of its prints with `phil/licensing-bundle` adds the type to the subjects it
prints unchecked.

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    printing:
        unlicensed_subjects: [certificate]
```

The bundle does not add it itself, and does not depend on `phil/licensing-bundle`: which documents a site prints
without a license is the site's decision. A site without that bundle has nothing to configure; a listener of its own
on `PrintStarting` reads `$event->subject->type` and lets `certificate` through the same way.

## The services

`Courses`, `Awarding`, `Revocation`, `ExpiryReminders`, `ContinuingEducation` and `CertificatePdf` are registered by
their class and can be decorated: a decorator of `Awarding` that sends the learner their certificate by e-mail, for
instance.
