# Courses and awarding

## Courses

A course is what a certificate recognizes. `Phil\CertificatesBundle\Application\Courses` keeps them, each known by its
slug:

```php
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;

$courses->define(
    'first-aid',                                        // the slug, in the register's URLs
    'First aid',                                        // the name, printed on the certificate
    'The gestures that save lives.',                    // the description
    'Score 70 % or more on the final assessment.',     // the narrative of the criteria, stated by the credential
    new PassCriteria(minimumScore: 70, minimumMinutes: 420, requiredModules: ['cpr', 'burns']),
    validityMonths: 24,                                 // forever when null
    image: 'https://example.org/badges/first-aid.png', // the badge's image, an absolute URL
    recertificationCredits: Credits::fromString('12'), // credits a recertification asks for; none when null
    endsAt: new DateTimeImmutable('2030-01-01 UTC'),    // when the course ends; never when null
);
```

| Field | Rule |
|---|---|
| Slug | Up to 64 lower-case letters and digits, single dashes between them; unique |
| Name | 1 to 255 characters |
| Narrative | Not blank: Open Badges requires the criteria of an achievement |
| Minimum score | 1 to 100, in percent |
| Minimum of minutes | At least 1 |
| Required modules | Up to 100 codes of 1 to 64 characters, each once |
| Validity | 1 to 1200 months |
| Image | An absolute HTTP URL of up to 2048 characters |
| Recertification credits | More than 0, up to two decimals; null when a recertification asks for an attainment |
| End | Any moment; no certificate is awarded from then on, nor valid past it |
| Completion source | 1 to 255 characters, without spaces nor control characters; null when the application alone decides who passed |

A value outside these rules throws `InvalidValue`; a slug already taken, `CertificateRefused`. `revise()` changes what a
course says and asks: certificates already awarded keep the credential they were given. `retire()` stops a course from
awarding certificates, `reopen()` lets it again; those awarded stay valid. A course past its end awards no more
either; [Recertification](recertification.md) covers the end of courses and certificates. A course that names a
completion source is awarded only to a member another system says completed it ([Completion sources](completion-sources.md)).

`phil:certificates:course` defines or revises a course from the console ([Console commands](console.md#courses)).

## Awarding

`Phil\CertificatesBundle\Application\Awarding` awards the certificate of a course to a learner whose attainment meets
its criteria:

```php
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;

$certificate = $awarding->award(
    'first-aid',
    'Ada Lovelace',                                     // the name printed on the certificate
    'ada@example.org',                                  // the identity the credential is issued to
    new Attainment(score: 84, minutes: 450, completedModules: ['cpr', 'burns']),
    listed: true,                                       // shown on the course's page in the register
    holder: 'member-42',                                // the member's stable identifier, for its credits
);
```

The application measures the attainment — an assessment's score, the minutes followed, the modules completed — and
decides when to award: after a final assessment, on a trainer's confirmation. The bundle checks it against the criteria
and refuses with `CertificateRefused` a course unknown, retired or ended, or criteria not met; the message lists what is
lacking: `score`, `minutes`, `module:<code>`.

The identity is a decentralized identifier (`did:…`), an e-mail address or a URL the learner controls. An address or a
URL is kept in the credential as a salted SHA-256 hash, the salt drawn for each certificate, and nowhere else: the
certificate stores no e-mail address. Whoever holds the credential and knows the address can check it against the
hash, as the verification page of `phil/open-badges-bundle` does.

The certificate then holds:

| Field | Value |
|---|---|
| `id` | A UUID; the credential's identifier is `urn:uuid:<id>` |
| `code` | A `VerificationCode` of twelve characters, printed `7KQ2-M9XA-04TZ` |
| `awardedAt`, `validUntil` | Now, from the clock; now plus the course's months, never past the course's end, or null |
| `statusIndex` | The next index in the status lists ([Revocation](revocation.md)) |
| `credential` | The `OpenBadgeCredential`, signed as `phil_open_badges.securing` says: a token or a document |
| `listed` | Whether the course's page lists it |
| `holder` | The member's stable identifier when the application gave one; an e-mail address is refused |
| `renews` | The certificate it renews, when a recertification awarded it |
| `remindedAt` | When its holder was last reminded of its end |
| `completion` | The identifier of the completion it was awarded on, for a course that names a completion source |

The credential's achievement is the course: its identifier is the URL of the course's page in the register, its name,
description, criteria and image the course's. The course's later revisions do not change it.

Listing is the learner's choice: a certificate is unlisted unless the application says otherwise, and is found only by
its code.

## Two awards at once

Each certificate takes the next status index, one past the highest given. With a lock factory — the one FrameworkBundle
registers when `symfony/lock` is installed, or the one `lock_factory` names —, `Awarding` takes the index, signs the
credential and stores the certificate under the lock `phil_certificates.status_index`, so that two awards at once never
reach for the same index. The lock's store is shared by every server that awards: the default `flock` store of
FrameworkBundle serves one server only, and a site on several servers configures a Redis, PDO or PostgreSQL store.
The lock is released once the certificate is stored; an award made inside a transaction the application opened is
committed later, so it is made outside one.

Without a lock factory — `lock_factory: null`, or no lock component —, or when a server awards with a store the
others do not share, the storage's unique status index refuses the second of two awards that took the same index. The
repository then throws `Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken`, and `Awarding` starts that award
again: the course read anew, the next index, a new code and identifier, the credential signed again. It does so
`Awarding::ATTEMPTS` times — three — at most; past them, `StatusIndexTaken` reaches the caller and nothing was stored
or dispatched.

The Doctrine repository throws `StatusIndexTaken` once it has reset the entity manager, which Doctrine closes after a
failed flush, so that the next attempt runs on an open one; entities the application loaded from that manager before
the award are detached and read again. A repository of the application's own throws `StatusIndexTaken` when its
storage refuses a certificate for an index or a code another holds, once it is ready for another attempt.

The lock lives 30 seconds, for reading the next index, signing the credential and storing the certificate. Signing
with a local key takes milliseconds; a signer that calls a remote key service is what can outlast the lock. An award
whose lock expired is still safe: the storage's unique indexes on the status index, the code and the completion of the
course are the claim that decides, so a second award that took the same index meanwhile is refused with
`StatusIndexTaken` and tried again, and a second award on the same completion finds the first. Keep a remote signer's
timeout well under 30 seconds, so that awards rarely take that path.

## After an award or a revocation

With an event dispatcher, `Awarding` dispatches `Phil\CertificatesBundle\Domain\Event\CertificateAwarded` once the
certificate is stored — followed by `CertificateRenewed` for a recertification —, and `Revocation` dispatches
`CertificateRevoked`. Each carries the certificate's identifier,
its verification code and its course's slug, and nothing about the learner: a listener that sends the PDF, or queues a
message that does, reads the certificate from `CertificateRepository::findByCode()`.

```php
#[AsEventListener]
final readonly class SendCertificate
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CertificateAwarded $awarded): void
    {
        $this->bus->dispatch(new SendCertificateMessage($awarded->certificateId));
    }
}
```

The [console commands](console.md) award and revoke through the same services, so their certificates are announced
too.
