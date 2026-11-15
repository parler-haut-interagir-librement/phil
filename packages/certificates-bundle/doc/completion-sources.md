# Completion sources

A certificate course can be awarded on what another system knows: a course catalogue, a learning platform, the
application's own lessons. That system keeps its own model of courses, learners and progress; the bundle keeps its
own courses and certificates. The two meet through one port, `CourseCompletions`, which the bundle asks and the other
system answers with an identifier.

## The port

```php
namespace Phil\CertificatesBundle\Domain\Contract;

interface CourseCompletions
{
    /** opaque non-empty identifier of the completion, or null when not completed */
    public function completion(string $course, string $holder): ?string;
}
```

| Argument | What it is |
|---|---|
| `$course` | The completion source the certificate course names: the key of the course in the other system |
| `$holder` | The member's stable identifier, the one the award names with `holder` — never an e-mail address |
| Answer | The identifier of the completion in the other system — an enrolment, a completion record, an attempt —, or `null` when the member has not completed the course |

The answer is kept on the certificate, as it is, as the evidence the certificate was awarded on. It has from 1 to 255
characters, without spaces nor control characters; an answer outside these rules is refused rather than stored. An
implementation answers from its own storage, at once, and keeps nothing from one request to the next.

The application registers its implementation under the interface:

```yaml
# config/services.yaml
services:
    Phil\CertificatesBundle\Domain\Contract\CourseCompletions:
        alias: App\Learning\CompletionsOfMyCourses
```

`phil/courses-bundle` is one implementation, for the courses it serves; any other system implements the interface the
same way, in a class of the application's own that reads that system.

## A course awarded on completion

A course names its completion source when it is defined, and changes or removes it afterwards:

```php
$courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Complete every lesson of the course.', completionSource: 'first-aid-2027');

$courses->changeCompletionSource('first-aid', 'first-aid-2028'); // another key
$courses->changeCompletionSource('first-aid', null);             // the application alone decides again
```

The key follows the same rules as a completion's identifier. `Courses::revise()` leaves it as it is. A course without
a completion source is awarded as [Courses and awarding](courses-and-awarding.md) describes, and the port is never
asked about it.

## Awarding

`Awarding::award()` asks the port for a course that names a completion source, after the course is found open and the
attainment meets its criteria:

```php
$certificate = $awarding->award('first-aid', 'Ada Lovelace', 'ada@example.org', holder: 'member-42');
$certificate->completion; // the identifier the source answered
```

The award is refused with `CertificateRefused`, and nothing is stored or dispatched, when:

| Case | Message |
|---|---|
| The award names no holder | `The course "first-aid" is awarded on completion: the award names the holder whose completion is asked.` |
| No `CourseCompletions` is registered | `The course "first-aid" is awarded on completion, and no source of completions is configured.` |
| The source answers `null` | `The holder has not completed the course "first-aid".` |
| The source answers an identifier outside the rules | `The completion source of the course "first-aid" answered an identifier of a completion that cannot be kept.` |
| A certificate of the course was already awarded on that completion | `The certificate 7KQ2-M9XA-04TZ of the course "first-aid" was already awarded on that completion.` |

A completion is awarded one certificate of a course. The completion is looked for, and the certificate stored, under the
lock of the status index; without a lock, the storage's unique index on the course and the completion refuses the
second of two awards at once, which starts again and then finds the first.

The criteria still apply: a course awarded on completion that asks for a score, minutes or modules is passed by an
attainment that meets them as well. A course whose completion stands for everything asks for none.

A recertification awards a new certificate by credits of continuing education or by an attainment, as
[Expiry and recertification](recertification.md) describes; it does not ask the port again, and the new certificate
names no completion.

## Awarding when the other system says so

`Awarding::awardOnCompletion()` is the entry point of a listener of the other system's own completion event. It
awards the certificate as `award()` does, and gives back the certificate already awarded on that completion, without
dispatching anything again, instead of refusing it: a listener that hears the same completion twice — a message
delivered again, a retry — awards once.

```php
use Phil\CertificatesBundle\Application\Awarding;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AwardCertificateOnCompletion
{
    public function __construct(
        private Awarding $awarding,
        private Members $members,
    ) {
    }

    public function __invoke(CourseCompleted $completed): void
    {
        $member = $this->members->get($completed->memberId);

        $this->awarding->awardOnCompletion('first-aid', $completed->memberId, $member->displayName, $member->email);
    }
}
```

`CourseCompleted` and `Members` stand for the application's own event and storage. The event carries identifiers only;
the name and the identity the credential is issued to are read where the application keeps them, at the moment of the
award. A message the listener queues instead carries the identifiers too, and its handler reads the rest.

`awardOnCompletion()` refuses a course that names no completion source, and an award whose holder is not a stable
identifier. `CertificateAwarded` carries the certificate's identifier, its code and its course's slug; the completion
is read from the certificate.

## The console

```sh
bin/console phil:certificates:course first-aid "First aid" "Complete every lesson." --completion-source=first-aid-2027
bin/console phil:certificates:award first-aid "Ada Lovelace" ada@example.org --holder=member-42 --on-completion
```

[Console commands](console.md) describes the options.

## The schema

The course keeps its key in `phil_certificates_course.completion_source`, the certificate its completion in
`phil_certificates_certificate.completion`, both nullable, with a unique index on `course_id` and `completion`. A
migration generated by Doctrine adds the two columns and the index; the rows already stored keep `NULL`, and their
courses are awarded as before.
