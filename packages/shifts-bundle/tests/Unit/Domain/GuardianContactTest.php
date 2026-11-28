<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ShiftsBundle\Domain\Exception\InvalidGuardianContact;
use Phil\ShiftsBundle\Domain\Model\Absence;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GuardianContact::class)]
#[CoversClass(InvalidGuardianContact::class)]
#[CoversClass(Absence::class)]
final class GuardianContactTest extends TestCase
{
    public function testAContactKeepsANameAWayToReachAndTheConsent(): void
    {
        $contact = new GuardianContact('ada', '  Marie Lovelace ', ' +1 514-555-0100 ', ' ', 'grace');

        self::assertSame('Marie Lovelace', $contact->name);
        self::assertSame('+1 514-555-0100', $contact->phone);
        self::assertNull($contact->email);
        self::assertFalse($contact->hasConsent());

        $at = new DateTimeImmutable('2026-11-27T10:00:00Z');
        $consented = $contact->withConsent($at, 'bob');
        self::assertTrue($consented->hasConsent());
        self::assertSame($at, $consented->consentedAt);
        self::assertSame('bob', $consented->consentRecordedBy);
        self::assertSame('grace', $consented->recordedBy);
        self::assertSame('Marie Lovelace', $consented->name);
        self::assertSame('+1 514-555-0100', $consented->phone);

        $withdrawn = $consented->withoutConsent();
        self::assertFalse($withdrawn->hasConsent());
        self::assertNull($withdrawn->consentRecordedBy);
        self::assertSame('grace', $withdrawn->recordedBy);

        $mail = new GuardianContact('ada', 'Marie', null, 'marie@example.org', 'grace');
        self::assertSame('marie@example.org', $mail->email);
        self::assertNull($mail->phone);
        self::assertSame(str_repeat('é', 120), new GuardianContact('ada', str_repeat('é', 120), '5550100', null, 'grace')->name);
        self::assertSame('(514) 555.0100', new GuardianContact('ada', 'Marie', '(514) 555.0100', null, 'grace')->phone);
        self::assertSame('+'.str_repeat('1', 31), new GuardianContact('ada', 'Marie', '+'.str_repeat('1', 31), null, 'grace')->phone);
        self::assertSame(str_repeat('a', 64).'@'.str_repeat('b', 185).'.org', new GuardianContact('ada', 'Marie', null, str_repeat('a', 64).'@'.str_repeat('b', 185).'.org', 'grace')->email);
        self::assertSame('Łukasz Ý', new GuardianContact('ada', 'Łukasz Ý', '5550100', null, 'grace')->name);
        self::assertSame(str_repeat('é', 40).'@example.org', new GuardianContact('ada', 'Marie', null, str_repeat('é', 40).'@example.org', 'grace')->email);
        self::assertSame('Ý@example.org', new GuardianContact('ada', 'Marie', null, 'Ý@example.org', 'grace')->email);
        self::assertSame(str_repeat('p', 191), new GuardianContact(str_repeat('p', 191), 'Marie', '5550100', null, 'grace')->volunteer);
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string, string, ?DateTimeImmutable, ?string, string}>
     */
    public static function refused(): iterable
    {
        $at = new DateTimeImmutable('2026-11-27T10:00:00Z');

        yield 'no name' => ['ada', ' ', '5550100', null, 'grace', null, null, InvalidGuardianContact::NAME];
        yield 'long name' => ['ada', str_repeat('é', 121), '5550100', null, 'grace', null, null, InvalidGuardianContact::NAME];
        yield 'control in name' => ['ada', "Marie\u{200B}", '5550100', null, 'grace', null, null, InvalidGuardianContact::NAME];
        yield 'newline in name' => ['ada', "Ma\nrie", '5550100', null, 'grace', null, null, InvalidGuardianContact::NAME];
        yield 'bad bytes in name' => ['ada', "Ma\xFFrie", '5550100', null, 'grace', null, null, InvalidGuardianContact::NAME];
        yield 'letters in phone' => ['ada', 'Marie', '555-CALL', null, 'grace', null, null, InvalidGuardianContact::PHONE];
        yield 'short phone' => ['ada', 'Marie', '55', null, 'grace', null, null, InvalidGuardianContact::PHONE];
        yield 'long phone' => ['ada', 'Marie', '+'.str_repeat('1', 32), null, 'grace', null, null, InvalidGuardianContact::PHONE];
        yield 'phone ending with a dash' => ['ada', 'Marie', '555-0100-', null, 'grace', null, null, InvalidGuardianContact::PHONE];
        yield 'phone with a newline' => ['ada', 'Marie', "555\n0100", null, 'grace', null, null, InvalidGuardianContact::PHONE];
        yield 'no at' => ['ada', 'Marie', null, 'marie.example.org', 'grace', null, null, InvalidGuardianContact::EMAIL];
        yield 'two at' => ['ada', 'Marie', null, 'ma@rie@example.org', 'grace', null, null, InvalidGuardianContact::EMAIL];
        yield 'no dot in domain' => ['ada', 'Marie', null, 'marie@localhost', 'grace', null, null, InvalidGuardianContact::EMAIL];
        yield 'space' => ['ada', 'Marie', null, 'ma rie@example.org', 'grace', null, null, InvalidGuardianContact::EMAIL];
        yield 'long local part' => ['ada', 'Marie', null, str_repeat('a', 65).'@example.org', 'grace', null, null, InvalidGuardianContact::EMAIL];
        yield 'long address' => ['ada', 'Marie', null, 'a@'.str_repeat('b', 249).'.org', 'grace', null, null, InvalidGuardianContact::EMAIL];
        yield 'no way to reach' => ['ada', 'Marie', null, '', 'grace', null, null, InvalidGuardianContact::NO_WAY_TO_REACH];
        yield 'empty volunteer' => ['', 'Marie', '5550100', null, 'grace', null, null, InvalidGuardianContact::PERSON];
        yield 'long volunteer' => [str_repeat('p', 192), 'Marie', '5550100', null, 'grace', null, null, InvalidGuardianContact::PERSON];
        yield 'padded recorder' => ['ada', 'Marie', '5550100', null, ' grace', null, null, InvalidGuardianContact::PERSON];
        yield 'bad bytes in volunteer' => ["a\xFF", 'Marie', '5550100', null, 'grace', null, null, InvalidGuardianContact::PERSON];
        yield 'consent without its recorder' => ['ada', 'Marie', '5550100', null, 'grace', $at, null, InvalidGuardianContact::PERSON];
        yield 'recorder without a consent' => ['ada', 'Marie', '5550100', null, 'grace', null, 'bob', InvalidGuardianContact::PERSON];
        yield 'empty consent recorder' => ['ada', 'Marie', '5550100', null, 'grace', $at, '', InvalidGuardianContact::PERSON];
    }

    #[DataProvider('refused')]
    public function testAContactOutsideItsBoundsIsRefused(string $volunteer, string $name, ?string $phone, ?string $email, string $recordedBy, ?DateTimeImmutable $at, ?string $consentBy, string $reason): void
    {
        try {
            new GuardianContact($volunteer, $name, $phone, $email, $recordedBy, $at, $consentBy);
            self::fail('The contact is refused.');
        } catch (InvalidGuardianContact $invalidGuardianContact) {
            self::assertSame($reason, $invalidGuardianContact->reason);
            self::assertSame(\sprintf('The contact of the guardian is refused: %s.', $reason), $invalidGuardianContact->getMessage());
        }
    }

    public function testAnAbsenceCountsUntilItIsLifted(): void
    {
        $at = new DateTimeImmutable('2026-11-28T10:00:00Z');

        self::assertFalse(new Absence('desk-am', 'ada', $at, 'grace')->isLifted());
        self::assertTrue(new Absence('desk-am', 'ada', $at, 'grace', $at, 'olga')->isLifted());
    }
}
