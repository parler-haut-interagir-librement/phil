<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Secret;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Secret\KeyDerivation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyDerivation::class)]
final class KeyDerivationTest extends TestCase
{
    public function testAKeyIsTheHkdfSha256OfTheSecretUnderItsLabel(): void
    {
        $key = KeyDerivation::key('secret', KeyDerivation::CONSENT);

        self::assertSame(hash_hkdf('sha256', 'secret', 32, 'phil_privacy.consent'), $key);
        self::assertSame(32, \strlen($key));
        self::assertNotSame(hash_hmac('sha256', 'phil_privacy.consent', 'secret', true), $key);
    }

    public function testTheRfc5869VectorWithoutSaltComesOut(): void
    {
        // RFC 5869, appendix A.3: SHA-256, empty salt and empty info; the info is our label, so it is checked apart.
        $ikm = (string) hex2bin(str_repeat('0b', 22));

        self::assertSame(
            '8da4e775a563c18f715f802a063c5a31b8a11f5c5ee1879ec3454e5f3c738d2d',
            bin2hex(hash_hkdf('sha256', $ikm, 32, '')),
        );
        self::assertSame(hash_hkdf('sha256', $ikm, 32, 'phil_privacy.consent'), KeyDerivation::key($ikm, KeyDerivation::CONSENT));
    }

    public function testEveryUsageHasItsOwnLabelAndItsOwnKey(): void
    {
        $labels = [
            KeyDerivation::GUEST_REQUESTS_CLIENT,
            KeyDerivation::GUEST_REQUESTS_ADDRESS,
            KeyDerivation::GUEST_REQUEST_STORE,
            KeyDerivation::EXPORT_ARCHIVE,
            KeyDerivation::CONSENT,
        ];

        self::assertSame([
            'phil_privacy.guest_requests.client',
            'phil_privacy.guest_requests.address',
            'phil_privacy.guest_request_store',
            'phil_privacy.export_archive',
            'phil_privacy.consent',
        ], $labels);
        self::assertCount(5, array_unique(array_map(static fn (string $label): string => KeyDerivation::key('secret', $label), $labels)));
    }

    public function testAnotherSecretGivesAnotherKey(): void
    {
        self::assertNotSame(KeyDerivation::key('secret', KeyDerivation::CONSENT), KeyDerivation::key('other', KeyDerivation::CONSENT));
    }

    public function testAnEmptySecretIsRefused(): void
    {
        try {
            KeyDerivation::key('', KeyDerivation::CONSENT);
            self::fail('An empty secret must not give a key.');
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            self::assertSame('The application secret is empty: set kernel.secret to derive the keys of the bundle.', $invalidPrivacyValue->getMessage());
        }
    }
}
