<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddresses::class)]
final class EmailAddressesTest extends TestCase
{
    #[TestWith(['Ada@Example.ORG', 'ada@example.org'])]
    #[TestWith(['  ada.lovelace+news@example.org ', 'ada.lovelace+news@example.org'])]
    #[TestWith(['ÉLOÏSE@exemple.ca', 'éloïse@exemple.ca'])]
    #[TestWith(["E\u{301}loi\u{308}se@exemple.ca", 'éloïse@exemple.ca'])]
    #[TestWith(['ada@BÜCHER.de', 'ada@xn--bcher-kva.de'])]
    #[TestWith(['"Ada L"@example.org', '"ada l"@example.org'])]
    #[TestWith(['ada@[192.0.2.1]', 'ada@[192.0.2.1]'])]
    public function testTheNormalizedFormFoldsTheCaseAndKeepsTheMailbox(string $address, string $normalized): void
    {
        self::assertSame($normalized, new EmailAddresses('key')->normalize($address));
    }

    #[TestWith([''])]
    #[TestWith(['ada'])]
    #[TestWith(['ada@'])]
    #[TestWith(['ada..lovelace@example.org'])]
    public function testATextThatIsNoAddressHasNoNormalizedForm(string $text): void
    {
        $addresses = new EmailAddresses('key');

        self::assertNull($addresses->normalize($text));
        self::assertNull($addresses->fingerprint($text));
        self::assertFalse($addresses->isRole($text));
        self::assertFalse($addresses->same($text, $text));
    }

    public function testTwoAddressesAreTheSameOnceNormalized(): void
    {
        $addresses = new EmailAddresses('key');

        self::assertTrue($addresses->same('Ada@Example.ORG', 'ada@example.org'));
        self::assertFalse($addresses->same('ada+news@example.org', 'ada@example.org'));
        self::assertFalse($addresses->same('a.da@example.org', 'ada@example.org'));
        self::assertFalse($addresses->same('ada@example.org', 'not an address'));
    }

    public function testTheFingerprintIsKeyedAndTakenOfTheNormalizedForm(): void
    {
        $addresses = new EmailAddresses('the key');

        self::assertEquals(AddressFingerprint::of('ada@example.org', 'the key'), $addresses->fingerprint('Ada@EXAMPLE.org'));
    }

    #[TestWith(['info@example.org', true])]
    #[TestWith(['INFO@example.org', true])]
    #[TestWith(['support+billing@example.org', true])]
    #[TestWith(['no-reply@example.org', true])]
    #[TestWith(['ada@example.org', false])]
    #[TestWith(['information.desk@example.org', false])]
    #[TestWith(['+info@example.org', false])]
    public function testARoleAddressIsTold(string $address, bool $role): void
    {
        self::assertSame($role, new EmailAddresses('key')->isRole($address));
    }

    public function testTheRolesCanBeReplaced(): void
    {
        $addresses = new EmailAddresses('key', ['Desk', 'team']);

        self::assertTrue($addresses->isRole('desk@example.org'));
        self::assertFalse($addresses->isRole('info@example.org'));
        self::assertSame(['desk', 'team'], $addresses->roleLocalParts());
        self::assertSame(EmailAddresses::ROLE_LOCAL_PARTS, new EmailAddresses('key')->roleLocalParts());
    }
}
