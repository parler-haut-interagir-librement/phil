<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\Identifier;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(OrganizationId::class)]
#[CoversClass(TeamId::class)]
#[CoversClass(InvitationId::class)]
#[CoversClass(MemberId::class)]
#[CoversClass(Roles::class)]
#[CoversClass(OrganizationName::class)]
#[CoversClass(EmailDomain::class)]
#[CoversClass(Moment::class)]
#[CoversClass(InvalidOrganization::class)]
final class ValueObjectsTest extends TestCase
{
    public function testAnIdentifierIsKeptAsGiven(): void
    {
        self::assertSame('01J.abc_DEF-9', MemberId::fromString('01J.abc_DEF-9')->value);
        self::assertSame(str_repeat('a', 128), OrganizationId::fromString(str_repeat('a', 128))->value);
        self::assertSame('t1', TeamId::fromString('t1')->value);
        self::assertSame('i1', InvitationId::fromString('i1')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'leading dash' => ['-abc'];
        yield 'leading dot' => ['.abc'];
        yield 'space' => ['a b'];
        yield 'colon' => ['a:b'];
        yield 'line feed at the end' => ["abc\n"];
        yield 'accent' => ['é'];
    }

    #[DataProvider('badIdentifiers')]
    public function testAnIdentifierOutOfShapeIsRefused(string $value): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('A member identifier is 1 to 128 letters, digits, "_", "." or "-", starting with a letter or a digit;');

        MemberId::fromString($value);
    }

    public function testAnEmailAddressIsNeverAnIdentifier(): void
    {
        try {
            MemberId::fromString('ann@example.org');
            self::fail('An address was taken.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertInstanceOf(OrganizationsException::class, $invalidOrganization);
            self::assertSame('A member identifier is a stable identifier, never an email address.', $invalidOrganization->getMessage());
        }
    }

    public function testEachIdentifierNamesWhatItIs(): void
    {
        foreach ([[OrganizationId::fromString(...), 'organization'], [TeamId::fromString(...), 'team'], [InvitationId::fromString(...), 'invitation']] as [$make, $what]) {
            try {
                $make('a b');
                self::fail('A bad identifier was taken.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertStringStartsWith(\sprintf('A %s identifier is', $what), $invalidOrganization->getMessage());
            }
        }
    }

    public function testGeneratedIdentifiersAreUuidsVersionSeven(): void
    {
        foreach ([OrganizationId::generate()->value, TeamId::generate()->value, InvitationId::generate()->value] as $id) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id);
        }

        self::assertNotSame(OrganizationId::generate()->value, OrganizationId::generate()->value);
    }

    public function testIdentifiersCompareByValue(): void
    {
        self::assertTrue(OrganizationId::fromString('a')->equals(OrganizationId::fromString('a')));
        self::assertFalse(OrganizationId::fromString('a')->equals(OrganizationId::fromString('A')));
        self::assertTrue(TeamId::fromString('a')->equals(TeamId::fromString('a')));
        self::assertFalse(TeamId::fromString('a')->equals(TeamId::fromString('b')));
        self::assertTrue(InvitationId::fromString('a')->equals(InvitationId::fromString('a')));
        self::assertFalse(InvitationId::fromString('a')->equals(InvitationId::fromString('b')));
        self::assertTrue(MemberId::fromString('a')->equals(MemberId::fromString('a')));
        self::assertFalse(MemberId::fromString('a')->equals(MemberId::fromString('b')));
    }

    public function testRolesAreSortedWithoutRepeats(): void
    {
        $roles = Roles::of(['member', 'admin', 'member']);

        self::assertSame(['admin', 'member'], $roles->names);
        self::assertSame('admin member', $roles->toString());
        self::assertTrue($roles->equals(Roles::fromString('member admin')));
        self::assertFalse($roles->equals(Roles::of(['admin'])));
        self::assertTrue($roles->has('admin'));
        self::assertFalse($roles->has('owner'));
        self::assertSame(['a_b9', 'z'], Roles::of(['z', 'a_b9'])->names);
    }

    public function testSixteenRolesAtMost(): void
    {
        $names = array_map(static fn (int $i): string => 'r'.$i, range(1, 16));
        self::assertCount(16, Roles::of($names)->names);

        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('A membership holds at most 16 roles.');

        Roles::of([...$names, 'r17']);
    }

    public function testAtLeastOneRole(): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('A membership holds at least one role.');

        Roles::of([]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badRoles(): iterable
    {
        yield 'uppercase' => ['Admin', 'Admin'];
        yield 'leading digit' => ['1admin', '1admin'];
        yield 'dot' => ['a.b', 'a.b'];
        yield 'empty' => ['', ''];
        yield 'too long' => [str_repeat('a', 65), str_repeat('a', 65)];
        yield 'line feed' => ["admin\n", "admin\n"];
        yield 'number' => [12, '12'];
        yield 'array' => [[], 'array'];
    }

    #[DataProvider('badRoles')]
    public function testARoleOutOfShapeIsRefused(mixed $role, string $shown): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A role is 1 to 64 lowercase letters, digits or "_", starting with a letter; "%s" is not.', $shown));

        Roles::of([$role]);
    }

    public function testARoleOfSixtyFourCharacters(): void
    {
        self::assertSame([str_repeat('a', 64)], Roles::of([str_repeat('a', 64)])->names);
    }

    public function testANameIsTrimmed(): void
    {
        self::assertSame('Acme Guild', OrganizationName::fromString('  Acme Guild ')->value);
        self::assertSame(str_repeat('é', 200), OrganizationName::fromString(str_repeat('é', 200))->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => ['   ', 'A name is UTF-8 text of at least one character, without control characters.'];
        yield 'control' => ["Acme\x07", 'A name is UTF-8 text of at least one character, without control characters.'];
        yield 'line feed inside' => ["Ac\nme", 'A name is UTF-8 text of at least one character, without control characters.'];
        yield 'not UTF-8' => ["\xC3\x28", 'A name is UTF-8 text of at least one character, without control characters.'];
        yield 'too long' => [str_repeat('é', 201), 'A name is at most 200 characters.'];
    }

    #[DataProvider('badNames')]
    public function testANameOutOfShapeIsRefused(string $name, string $message): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains($message);

        OrganizationName::fromString($name);
    }

    public function testADomainIsLowercase(): void
    {
        self::assertSame('example.org', EmailDomain::fromString('Example.ORG')->value);
        self::assertSame('xn--bcher-kva.example', EmailDomain::fromString('xn--bcher-kva.example')->value);
        self::assertSame('a.b.c', EmailDomain::ofAddress('ann@Lab@A.b.C')->value);
        self::assertTrue(EmailDomain::fromString('a.org')->equals(EmailDomain::fromString('A.ORG')));
        self::assertFalse(EmailDomain::fromString('a.org')->equals(EmailDomain::fromString('b.org')));
    }

    public function testADomainOfTwoHundredFiftyThreeCharacters(): void
    {
        $domain = implode('.', [str_repeat('a', 63), str_repeat('b', 63), str_repeat('c', 63), str_repeat('d', 61)]);
        self::assertSame(253, \strlen($domain));
        self::assertSame($domain, EmailDomain::fromString($domain)->value);

        $this->expectException(InvalidOrganization::class);

        EmailDomain::fromString($domain.'d');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badDomains(): iterable
    {
        yield 'one label' => ['localhost'];
        yield 'leading dash' => ['-a.org'];
        yield 'trailing dash' => ['a-.org'];
        yield 'empty label' => ['a..org'];
        yield 'trailing dot' => ['a.org.'];
        yield 'label too long' => [str_repeat('a', 64).'.org'];
        yield 'unicode' => ['bücher.example'];
        yield 'line feed' => ["a.org\n"];
    }

    #[DataProvider('badDomains')]
    public function testADomainOutOfShapeIsRefused(string $domain): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not an email domain of at least two labels, in ASCII.', $domain));

        EmailDomain::fromString($domain);
    }

    public function testAnAddressWithoutLocalPartOrAtIsRefused(): void
    {
        foreach (['@a.org', 'a.org'] as $address) {
            try {
                EmailDomain::ofAddress($address);
                self::fail('A bad address was taken.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame('An email address has a local part, an "@" and a domain.', $invalidOrganization->getMessage());
            }
        }

        self::assertSame('a.org', EmailDomain::ofAddress('x@a.org')->value);
    }

    public function testAMomentIsKeptToTheSecondInUtc(): void
    {
        $moment = Moment::of(new DateTimeImmutable('2026-11-17 10:00:00.75 America/Montreal'));

        self::assertSame('2026-11-17T15:00:00+00:00', $moment->format(\DATE_ATOM));
        self::assertSame('000000', $moment->format('u'));
        self::assertSame(86_400, Moment::fromTimestamp(86_400)->getTimestamp());
    }
}
