<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderGoogleBundle\Application\GoogleClaims;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GoogleClaims::class)]
final class GoogleClaimsTest extends TestCase
{
    public function testAGmailAddressVerifiedIsTrusted(): void
    {
        $identity = new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => '123', 'email' => 'Ada@GMail.com', 'email_verified' => true, 'name' => 'Ada']);

        self::assertSame('google', $identity->provider);
        self::assertSame('123', $identity->subject);
        self::assertSame('Ada@GMail.com', $identity->email);
        self::assertTrue($identity->emailVerified);
        self::assertNull($identity->displayName);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function verification(): iterable
    {
        yield 'a Workspace address on its domain' => [['email' => 'ada@example.com', 'email_verified' => true, 'hd' => 'Example.com'], true];
        yield 'the string "true" of older answers' => [['email' => 'ada@gmail.com', 'email_verified' => 'true'], true];
        yield 'a consumer account on another domain' => [['email' => 'ada@example.com', 'email_verified' => true], false];
        yield 'a Workspace address on another domain' => [['email' => 'ada@other.example', 'email_verified' => true, 'hd' => 'example.com'], false];
        yield 'a domain that ends like gmail' => [['email' => 'ada@notgmail.com', 'email_verified' => true], false];
        yield 'an address not verified' => [['email' => 'ada@gmail.com', 'email_verified' => false], false];
        yield 'a verification of 1' => [['email' => 'ada@gmail.com', 'email_verified' => 1], false];
        yield 'no verification' => [['email' => 'ada@gmail.com'], false];
        yield 'an empty domain' => [['email' => 'ada@gmail.com', 'email_verified' => true, 'hd' => ''], true];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider('verification')]
    public function testAnAddressIsTrustedOnlyWhenGoogleIsItsAuthority(array $claims, bool $verified): void
    {
        self::assertSame($verified, new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => '1'] + $claims)->emailVerified);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badAddresses(): iterable
    {
        yield 'none' => [null];
        yield 'not a string' => [['a@b.c']];
        yield 'without an at sign' => ['ada.example.com'];
        yield 'with two at signs' => ['ada@x@example.com'];
        yield 'with a space' => ['ada lovelace@example.com'];
        yield 'nothing before the at sign' => ['@example.com'];
        yield 'nothing after the at sign' => ['ada@'];
        yield 'too long' => [str_repeat('a', 245).'@gmail.com'];
    }

    #[DataProvider('badAddresses')]
    public function testAnAddressThatIsNotOneIsLeftOut(mixed $email): void
    {
        $identity = new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => '1', 'email' => $email, 'email_verified' => true]);

        self::assertNull($identity->email);
        self::assertFalse($identity->emailVerified);
    }

    public function testTheLongestAddressIsKept(): void
    {
        $email = str_repeat('a', 244).'@gmail.com';

        self::assertSame($email, new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => '1', 'email' => $email])->email);
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function names(): iterable
    {
        yield 'a name' => ['  Ada Lovelace ', 'Ada Lovelace'];
        yield 'control characters' => ["Ada\u{0}\u{200B}\nLovelace", 'Ada Lovelace'];
        yield 'blank' => [" \t ", null];
        yield 'not a string' => [42, null];
        yield 'not UTF-8' => ["Ada\xff", null];
        yield 'too long' => [str_repeat('é', 201), str_repeat('é', 200)];
    }

    #[DataProvider('names')]
    public function testTheNameIsKeptOnlyWhenAskedFor(mixed $name, ?string $kept): void
    {
        self::assertSame($kept, new GoogleClaims(new GoogleSettings('c', requestProfile: true))->identity(['sub' => '1', 'name' => $name])->displayName);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badSubjects(): iterable
    {
        yield 'none' => [null];
        yield 'empty' => [''];
        yield 'a number' => [123];
        yield 'with a space' => ['1 2'];
        yield 'too long' => [str_repeat('1', 256)];
        yield 'with a line break' => ["12\n"];
    }

    #[DataProvider('badSubjects')]
    public function testAClaimSetWithoutASubjectIsRefused(mixed $subject): void
    {
        $this->expectExceptionObject(Refused::because('external.failed'));

        new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => $subject]);
    }

    public function testTheLongestSubjectIsAccepted(): void
    {
        self::assertSame(str_repeat('~', 255), new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => str_repeat('~', 255)])->subject);
        self::assertSame('!', new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => '!'])->subject);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function otherDomains(): iterable
    {
        yield 'a consumer account' => [null];
        yield 'another domain' => ['other.example'];
        yield 'an empty domain' => [''];
    }

    #[DataProvider('otherDomains')]
    public function testWithAcceptedDomainsAnAccountOfNoneIsRefused(mixed $domain): void
    {
        $this->expectExceptionObject(Refused::because('external.failed'));

        new GoogleClaims(new GoogleSettings('c', ['example.com', 'example.org']))->identity(['sub' => '1', 'hd' => $domain]);
    }

    public function testWithAcceptedDomainsAnAccountOfOneIsAccepted(): void
    {
        $identity = new GoogleClaims(new GoogleSettings('c', ['example.com', 'example.org']))->identity(['sub' => '1', 'hd' => 'EXAMPLE.org', 'email' => 'ada@example.org', 'email_verified' => true]);

        self::assertTrue($identity->emailVerified);
    }

    public function testAnAddressEndingWithALineFeedIsLeftOut(): void
    {
        $identity = new GoogleClaims(new GoogleSettings('c'))->identity(['sub' => '1', 'email' => "ada@gmail.com\n", 'email_verified' => true]);

        self::assertNull($identity->email);
    }
}
