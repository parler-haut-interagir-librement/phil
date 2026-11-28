<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use Phil\ParticipantSafetyInfo\EmergencyContact;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\SafetyInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafetyInfo::class)]
final class SafetyInfoTest extends TestCase
{
    public function testItTrimsItsFieldsAndKeepsLinesAndTabs(): void
    {
        $info = new SafetyInfo([], " Peanuts\n\tand shellfish ", '  ', "\r\nnote\r\n");

        self::assertSame("Peanuts\n\tand shellfish", $info->allergies);
        self::assertSame('', $info->needs);
        self::assertSame('note', $info->notes);
        self::assertSame([], $info->contacts);
    }

    public function testAnyOneFieldOrOneContactIsEnough(): void
    {
        self::assertSame('a', new SafetyInfo(allergies: 'a')->allergies);
        self::assertSame('n', new SafetyInfo(needs: 'n')->needs);
        self::assertSame('o', new SafetyInfo(notes: 'o')->notes);
        self::assertCount(1, new SafetyInfo([new EmergencyContact('A', '123')])->contacts);
    }

    public function testItTakesThreeContactsAndFieldsOf2000Characters(): void
    {
        $contact = new EmergencyContact('A', '123');
        $info = new SafetyInfo([$contact, $contact, $contact], str_repeat('é', 2000), str_repeat('é', 2000), str_repeat('é', 2000));

        self::assertCount(3, $info->contacts);
        self::assertSame(2000, mb_strlen($info->allergies));
    }

    /**
     * @return iterable<string, array{list<EmergencyContact>, string, string, string, string}>
     */
    public static function refused(): iterable
    {
        $contact = new EmergencyContact('A', '123');
        $contacts = 'A participant names at most 3 emergency contacts.';
        $empty = 'Safety information holds at least one contact or one field; erase it instead of sending it empty.';

        yield 'four contacts' => [[$contact, $contact, $contact, $contact], 'a', '', '', $contacts];
        yield 'nothing' => [[], ' ', '', '', $empty];
        yield 'allergies of 2001 characters' => [[], str_repeat('é', 2001), '', '', 'The field "allergies" holds at most 2000 characters of text.'];
        yield 'needs with a null byte' => [[], '', "a\0b", '', 'The field "needs" holds at most 2000 characters of text.'];
        yield 'notes with an escape' => [[], '', '', "a\x1Bb", 'The field "notes" holds at most 2000 characters of text.'];
        yield 'notes with a delete' => [[], '', '', "a\x7Fb", 'The field "notes" holds at most 2000 characters of text.'];
        yield 'notes with a vertical tab' => [[], '', '', "a\x0Bb", 'The field "notes" holds at most 2000 characters of text.'];
        yield 'notes not in UTF-8' => [[], '', '', "\xC3", 'The field "notes" holds at most 2000 characters of text.'];
    }

    /**
     * @param list<EmergencyContact> $contacts
     */
    #[DataProvider('refused')]
    public function testAValueOutOfBoundsIsRefused(array $contacts, string $allergies, string $needs, string $notes, string $message): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SafetyInfo($contacts, $allergies, $needs, $notes);
    }

    public function testItIsNeverDumped(): void
    {
        $info = new SafetyInfo([], 'Peanuts');

        self::assertStringNotContainsString('Peanuts', print_r($info, true));
        self::assertSame(['contacts' => '[sensitive]', 'allergies' => '[sensitive]', 'needs' => '[sensitive]', 'notes' => '[sensitive]'], $info->__debugInfo());
    }
}
