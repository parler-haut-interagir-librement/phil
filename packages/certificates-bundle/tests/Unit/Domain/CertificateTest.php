<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Status\CertificateStatus;
use Phil\CertificatesBundle\Domain\ValueObject\OpaqueId;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Certificate::class)]
#[CoversClass(CertificateRefused::class)]
#[CoversClass(OpaqueId::class)]
final class CertificateTest extends TestCase
{
    public function testACertificateIsValidForTheMonthsOfItsCourse(): void
    {
        $certificate = Kit::awarded();

        self::assertSame(['0190f1d2-3c4b-7a8e-9f00-112233445566', '7KQ2M9XA04TZ', 'Ada Lovelace', 3, 'header.payload.signature', true], [$certificate->id, $certificate->code->value, $certificate->recipientName, $certificate->statusIndex, $certificate->credential, $certificate->listed]);
        self::assertSame('2026-11-08T10:00:00+00:00', $certificate->awardedAt->format('c'));
        self::assertSame('2027-11-08T10:00:00+00:00', $certificate->validUntil?->format('c'));
        self::assertNull(Kit::awarded(Kit::firstAid(null))->validUntil);
        self::assertSame('2026-03-28', Certificate::validUntil(Kit::firstAid(1), new DateTimeImmutable('2026-02-28 UTC'))?->format('Y-m-d'));
        self::assertNull(Certificate::validUntil(Kit::firstAid(null), new DateTimeImmutable('2026-02-28 UTC')));
        self::assertSame('first-aid', $certificate->course->slug);
        self::assertNull($certificate->revokedAt);
        self::assertNull($certificate->revocationReason);
    }

    public function testItsStatusFollowsItsValidityAndItsRevocation(): void
    {
        $certificate = Kit::awarded();

        self::assertSame(CertificateStatus::Valid, $certificate->status(new DateTimeImmutable('2027-11-08 09:59:59 UTC')));
        self::assertSame(CertificateStatus::Expired, $certificate->status(new DateTimeImmutable('2027-11-08 10:00:00 UTC')));
        self::assertSame(CertificateStatus::Valid, Kit::awarded(Kit::firstAid(null))->status(new DateTimeImmutable('2100-01-01 UTC')));

        $certificate->revoke('Fraud at the assessment.', new DateTimeImmutable('2026-12-01 UTC'));

        self::assertSame(CertificateStatus::Revoked, $certificate->status(new DateTimeImmutable('2026-12-02 UTC')));
        self::assertSame('2026-12-01', $certificate->revokedAt?->format('Y-m-d'));
        self::assertSame('Fraud at the assessment.', $certificate->revocationReason);
    }

    public function testACertificateIsRevokedOnce(): void
    {
        $certificate = Kit::awarded();
        $certificate->revoke(str_repeat('é', 500), new DateTimeImmutable('2026-12-01 UTC'));

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('The certificate 7KQ2-M9XA-04TZ is already revoked.');

        $certificate->revoke('Again.', new DateTimeImmutable('2026-12-02 UTC'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReasons(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'too long' => [str_repeat('é', 501)];
    }

    #[DataProvider('invalidReasons')]
    public function testARevocationStatesItsReason(string $reason): void
    {
        $certificate = Kit::awarded();

        try {
            $certificate->revoke($reason, new DateTimeImmutable('2026-12-01 UTC'));
            self::fail('The revocation is refused.');
        } catch (InvalidValue) {
            self::assertNull($certificate->revokedAt);
        }
    }

    public function testTheNameAndTheStatusIndexAreChecked(): void
    {
        self::assertSame(str_repeat('é', 255), Kit::awarded()::award('id', Kit::awarded()->code, Kit::firstAid(), str_repeat('é', 255), new DateTimeImmutable(), 0, 'x')->recipientName);
        self::assertFalse(Certificate::award('id', Kit::awarded()->code, Kit::firstAid(), 'A', new DateTimeImmutable(), 0, 'x')->listed);

        foreach ([['', 0], ['  ', 0], [str_repeat('é', 256), 0], ['A', -1]] as [$name, $index]) {
            try {
                Certificate::award('id', Kit::awarded()->code, Kit::firstAid(), $name, new DateTimeImmutable(), $index, 'x');
                self::fail('The certificate is refused.');
            } catch (InvalidValue $invalidValue) {
                self::assertNotSame('', $invalidValue->getMessage());
            }
        }
    }

    public function testACertificateKeepsTheCompletionItWasAwardedOn(): void
    {
        self::assertNull(Kit::awarded()->completion);
        self::assertSame('enrolment-7', Certificate::award('id', Kit::awarded()->code, Kit::firstAid(), 'A', new DateTimeImmutable(), 0, 'x', completion: 'enrolment-7')->completion);
        self::assertSame(str_repeat('c', 255), Certificate::award('id', Kit::awarded()->code, Kit::firstAid(), 'A', new DateTimeImmutable(), 0, 'x', completion: str_repeat('c', 255))->completion);
        self::assertSame(255, OpaqueId::MAX_LENGTH);

        foreach (['', str_repeat('c', 256), 'enrolment 7', "\x00", "a\x20b", "a\x7F"] as $completion) {
            try {
                Certificate::award('id', Kit::awarded()->code, Kit::firstAid(), 'A', new DateTimeImmutable(), 0, 'x', completion: $completion);
                self::fail('The completion is refused.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('The identifier of a completion has from 1 to 255 characters, without spaces nor control characters.', $invalidValue->getMessage());
            }
        }

        self::assertSame("!~\x21", OpaqueId::fromString("!~\x21", 'key')->value);
    }

    public function testACredentialIsATokenOrADocument(): void
    {
        self::assertTrue(Kit::awarded()->isToken());
        self::assertFalse(Kit::awarded(credential: '{"proof":{}}')->isToken());
    }
}
