<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Application;

use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerifiedIdentity::class)]
final class VerifiedIdentityTest extends TestCase
{
    public function testOnlyTheAllowedClaimsAreKept(): void
    {
        $identity = VerifiedIdentity::fromClaims('https://id.example.test', 'sub', ['name' => 'Ada', 'groups' => ['x']], ['name', 'locale']);

        self::assertSame('https://id.example.test', $identity->issuer);
        self::assertSame('sub', $identity->subject);
        self::assertSame(['name' => 'Ada'], $identity->claims);
    }

    public function testANullClaimIsKeptAsItWasAsserted(): void
    {
        self::assertSame(['locale' => null], VerifiedIdentity::fromClaims('i', 's', ['locale' => null], ['locale'])->claims);
    }

    public function testAVerifiedEmailIsKept(): void
    {
        $identity = VerifiedIdentity::fromClaims('i', 's', ['email' => 'a@example.test', 'email_verified' => true], ['email']);

        self::assertSame('a@example.test', $identity->verifiedEmail());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unverified(): iterable
    {
        yield 'false' => [['email' => 'a@example.test', 'email_verified' => false]];
        yield 'absent' => [['email' => 'a@example.test']];
        yield 'a truthy string' => [['email' => 'a@example.test', 'email_verified' => 'true']];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider('unverified')]
    public function testAnUnverifiedEmailIsDropped(array $claims): void
    {
        $identity = VerifiedIdentity::fromClaims('i', 's', $claims, ['email']);

        self::assertNull($identity->verifiedEmail());
        self::assertSame([], $identity->claims);
    }

    public function testAnUnverifiedPhoneIsDropped(): void
    {
        self::assertSame([], VerifiedIdentity::fromClaims('i', 's', ['phone_number' => '+1'], ['phone_number'])->claims);
        self::assertSame(['phone_number' => '+1'], VerifiedIdentity::fromClaims('i', 's', ['phone_number' => '+1', 'phone_number_verified' => true], ['phone_number'])->claims);
    }

    public function testAnEmptyOrNonTextEmailIsNoEmail(): void
    {
        self::assertNull(VerifiedIdentity::fromClaims('i', 's', ['email' => '', 'email_verified' => true], ['email'])->verifiedEmail());
        self::assertNull(VerifiedIdentity::fromClaims('i', 's', ['email' => 7, 'email_verified' => true], ['email'])->verifiedEmail());
    }
}
