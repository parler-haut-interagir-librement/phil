<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use Phil\ParticipantSafetyInfo\EmergencyContact;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmergencyContact::class)]
final class EmergencyContactTest extends TestCase
{
    public function testItTrimsItsFields(): void
    {
        $contact = new EmergencyContact('  Alex  ', ' (514) 555.0199 ', ' parent ');

        self::assertSame('Alex', $contact->name);
        self::assertSame('(514) 555.0199', $contact->phone);
        self::assertSame('parent', $contact->relation);
    }

    public function testItTakesItsLongestFields(): void
    {
        $contact = new EmergencyContact(str_repeat('é', 120), '+'.str_repeat('1', 31), str_repeat('é', 60));

        self::assertSame(120, mb_strlen($contact->name));
        self::assertSame(60, mb_strlen($contact->relation));
        self::assertSame(32, \strlen($contact->phone));
        self::assertSame('', new EmergencyContact('A', '123')->relation);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function refused(): iterable
    {
        $name = 'The name of an emergency contact holds 1 to 120 characters on one line.';
        $relation = 'The relation of an emergency contact holds at most 60 characters on one line.';
        $phone = 'The telephone number of an emergency contact holds 3 to 32 digits and separators.';

        yield 'an empty name' => ['  ', '123', '', $name];
        yield 'a name of 121 characters' => [str_repeat('é', 121), '123', '', $name];
        yield 'a name on two lines' => ["A\nB", '123', '', $name];
        yield 'a name ending with delete' => ["A\x7F", '123', '', $name];
        yield 'a name not in UTF-8' => ["\xFF", '123', '', $name];
        yield 'a relation of 61 characters' => ['A', '123', str_repeat('é', 61), $relation];
        yield 'a relation on two lines' => ['A', '123', "a\tb", $relation];
        yield 'a relation not in UTF-8' => ['A', '123', "\xFF", $relation];
        yield 'a number of two digits' => ['A', '12', '', $phone];
        yield 'a number with two digits among separators' => ['A', '1 - 2', '', $phone];
        yield 'a number of 33 characters' => ['A', str_repeat('1', 33), '', $phone];
        yield 'a number after a letter' => ['A', 'x5551234', '', $phone];
        yield 'a number with a letter' => ['A', '555-CALL', '', $phone];
        yield 'a number with a plus inside' => ['A', '555+1234', '', $phone];
        yield 'a number on two lines' => ['A', "555\n1234", '', $phone];
    }

    #[DataProvider('refused')]
    public function testAFieldOutOfBoundsIsRefused(string $name, string $phone, string $relation, string $message): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains($message);

        new EmergencyContact($name, $phone, $relation);
    }

    public function testItIsNeverDumped(): void
    {
        $dump = print_r(new EmergencyContact('Alex', '5551234', 'parent'), true);

        self::assertStringNotContainsString('Alex', $dump);
        self::assertSame(['name' => '[sensitive]', 'relation' => '[sensitive]', 'phone' => '[sensitive]'], new EmergencyContact('A', '123')->__debugInfo());
    }
}
