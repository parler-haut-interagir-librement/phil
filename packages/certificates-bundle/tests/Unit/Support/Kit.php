<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Verification\CredentialVerifier;

/**
 * The parts the unit tests share: the issuer and its fixed key, its verifier, a course and a certificate.
 */
final class Kit
{
    public const string ISSUER = 'https://example.com/open-badges/issuer';

    public const string METHOD = self::ISSUER.'#key-1';

    private function __construct()
    {
    }

    public static function signer(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(str_repeat("\1", 32));
    }

    public static function issuer(): Issuer
    {
        return new Issuer(self::ISSUER, 'Example Academy');
    }

    public static function verifier(): CredentialVerifier
    {
        return new CredentialVerifier(new DidKeyMethods([self::METHOD => self::signer()->verificationKey()]), self::METHOD);
    }

    public static function firstAid(?int $validityMonths = 12, PassCriteria $criteria = new PassCriteria(minimumScore: 70)): Course
    {
        return Course::define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more on the final assessment.', $criteria, $validityMonths, 'https://example.com/badges/first-aid.png');
    }

    public static function awarded(?Course $course = null, string $credential = 'header.payload.signature', bool $listed = true, string $code = '7KQ2M9XA04TZ', int $index = 3): Certificate
    {
        return Certificate::award('0190f1d2-3c4b-7a8e-9f00-112233445566', VerificationCode::fromString($code), $course ?? self::firstAid(), 'Ada Lovelace', new DateTimeImmutable('2026-11-08 10:00:00 UTC'), $index, $credential, $listed);
    }
}
