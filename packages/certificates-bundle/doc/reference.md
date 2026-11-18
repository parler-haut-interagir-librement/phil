# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\Model\Course` | A course: slug, name, description, narrative, criteria, validity, image, retired, recertification credits, end, completion source. |
| `Domain\Model\Certificate` | A certificate: code, course, name, dates, status index, credential, listed, revocation, holder, the certificate it renews, last reminder, the completion it was awarded on. |
| `Domain\Model\LearningActivity` | An activity of continuing education: member, title, credits, completion, course, source. |
| `Domain\ValueObject\PassCriteria` | The minimum score, minutes and modules of a course; what an attainment lacks. |
| `Domain\ValueObject\Attainment` | What a learner attained: score, minutes, modules completed. |
| `Domain\ValueObject\VerificationCode` | The twelve-character code of a certificate, generated and read back. |
| `Domain\ValueObject\Credits` | Credits of continuing education, to the hundredth. |
| `Domain\ValueObject\MemberId` | The stable identifier of a member; never an e-mail address. |
| `Domain\ValueObject\Period` | A period, its start included and its end excluded. |
| `Domain\ValueObject\OpaqueId` | An identifier another system gives: a course's completion source, a completion. |
| `Domain\Status\CertificateStatus` | Valid, expired or revoked. |
| `Domain\Contract\CourseRepository`, `CertificateRepository`, `LearningActivityRepository` | Where courses, certificates and activities are kept. |
| `Domain\Contract\CourseCompletions` | Whether a member completed a course of another system, and the identifier of that completion. |
| `Domain\Exception\CertificatesException` | The marker every exception of the bundle implements. |
| `Domain\Exception\InvalidValue` | A value that does not hold together. |
| `Domain\Exception\CertificateRefused` | An award or a revocation refused. |
| `Domain\Exception\StatusIndexTaken` | A certificate refused because another took its index, its code or its completion; the award starts again. |
| `Domain\Event\CertificateAwarded` | A certificate awarded and stored, by identifiers. |
| `Domain\Event\CertificateRevoked` | A certificate revoked and stored so, by identifiers. |
| `Domain\Event\CertificateRenewed` | A recertification's certificate stored, by identifiers. |
| `Domain\Event\CertificateExpiring` | A certificate within a reminder's threshold of its end, by identifiers and days left. |
| `Domain\Event\LearningActivityRecorded` | An activity of continuing education stored, by identifiers. |

## Application

| Class | Role |
|---|---|
| `Application\Courses` | Defines, revises, retires and reopens courses; names their completion source. |
| `Application\Awarding` | Awards a certificate with its signed credential, again when its index was taken, on a completion when its course names a source; recertifies. |
| `Application\ExpiryReminders` | Dispatches the reminders of the certificates about to end. |
| `Application\ContinuingEducation` | Records, lists and sums the credits of continuing education; a holder's progress. |
| `Application\Revocation` | Revokes a certificate. |
| `Application\CertificatePdf` | Renders a certificate's PDF, the credential attached, PDF/A-3b when asked. |
| `Application\StatusListLayout` | How status indexes fill the lists. |
| `Application\CertificateStatusLists` | The status lists of revocation, for `phil/open-badges-bundle`. |
| `Application\Contract\CertificateUrls` | The URLs a certificate points to. |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\DoctrineCourseRepository`, `DoctrineCertificateRepository`, `DoctrineLearningActivityRepository` | The repositories through Doctrine. |
| `Infrastructure\Doctrine\BinaryKeysListener` | The keys of the register in `utf8mb4_bin` on MySQL and MariaDB. |
| `Infrastructure\Routing\RoutedCertificateUrls` | The URLs from the register's routes. |
| `Infrastructure\Http\RegisterController` | The register's pages and downloads. |
| `Infrastructure\Console\CourseCommand` | `phil:certificates:course`. |
| `Infrastructure\Console\AwardCommand` | `phil:certificates:award`. |
| `Infrastructure\Console\RevokeCommand` | `phil:certificates:revoke`. |
| `Infrastructure\Console\StatusCommand` | `phil:certificates:status`. |
| `Infrastructure\Console\ExportCommand` | `phil:certificates:export`. |
| `Infrastructure\Console\RecertifyCommand` | `phil:certificates:recertify`. |
| `Infrastructure\Console\RemindCommand` | `phil:certificates:remind`. |
| `Infrastructure\Console\CreditCommand` | `phil:certificates:credit`. |
| `Infrastructure\Console\CreditsCommand` | `phil:certificates:credits`. |
| `Infrastructure\Console\Numbers` | Internal: the whole numbers and days typed on the command line. |
| `Infrastructure\DependencyInjection\CertificatesServices` | The bundle's services. |
| `PhilCertificatesBundle` | The configuration, the checks and the Doctrine mapping. |
