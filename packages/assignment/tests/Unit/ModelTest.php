<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Closure;
use Phil\Assignment\Applicant;
use Phil\Assignment\Exception\AssignmentException;
use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Internal\Identifier;
use Phil\Assignment\Limits;
use Phil\Assignment\Pair;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;
use Phil\Assignment\WorkBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slot::class)]
#[CoversClass(Applicant::class)]
#[CoversClass(Pair::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Seed::class)]
#[CoversClass(WorkBudget::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidProblem::class)]
#[CoversClass(BudgetExceeded::class)]
final class ModelTest extends TestCase
{
    public function testASlotHoldsItsCapacityAndMinimum(): void
    {
        $slot = new Slot('table-1', 5, 3);

        self::assertSame(['table-1', 5, 3], [$slot->id, $slot->capacity, $slot->minimum]);
        self::assertSame(0, new Slot('empty', 0)->minimum);
        self::assertSame(Slot::MAX_CAPACITY, new Slot('hall', Slot::MAX_CAPACITY, Slot::MAX_CAPACITY)->capacity);
    }

    public function testAnApplicantIsAPersonOrAGroup(): void
    {
        $person = Applicant::person('ana', ['t1', 't2']);
        $group = Applicant::group('g1', ['bo', 'cy'], ['t2']);

        self::assertSame(['ana'], $person->members);
        self::assertSame(1, $person->size());
        self::assertSame(['bo', 'cy'], $group->members);
        self::assertSame(2, $group->size());
        self::assertSame(1, $person->rankOf('t1'));
        self::assertSame(2, $person->rankOf('t2'));
        self::assertNull($person->rankOf('t3'));
        self::assertSame([], new Applicant('dee')->choices);
    }

    public function testAPairNamesAnApplicantAndASlot(): void
    {
        $pair = new Pair('ana', 't1');

        self::assertSame(['ana', 't1'], [$pair->applicant, $pair->slot]);
    }

    public function testLimitsHaveDefaults(): void
    {
        $limits = new Limits();

        self::assertSame([5_000, 20_000, 20, 50], [$limits->slots, $limits->applicants, $limits->choices, $limits->members]);
        self::assertSame(1, new Limits(1, 1, 1, 1)->members);
        self::assertSame(50_000_000, new WorkBudget()->operations);
        self::assertSame(1, new WorkBudget(1)->operations);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'negative capacity' => [static fn (): Slot => new Slot('t', -1), 'The capacity of slot "t" is 0 to 1000000.'];
        yield 'capacity too large' => [static fn (): Slot => new Slot('t', Slot::MAX_CAPACITY + 1), 'The capacity of slot "t" is 0 to 1000000.'];
        yield 'negative minimum' => [static fn (): Slot => new Slot('t', 3, -1), 'The minimum of slot "t" is 0 to its capacity.'];
        yield 'minimum above capacity' => [static fn (): Slot => new Slot('t', 3, 4), 'The minimum of slot "t" is 0 to its capacity.'];
        yield 'empty identifier' => [static fn (): Slot => new Slot('', 3), 'Slot identifiers are 1 to 255 bytes without control characters.'];
        yield 'long identifier' => [static fn (): Slot => new Slot(str_repeat('a', 256), 3), 'Slot identifiers are 1 to 255 bytes without control characters.'];
        yield 'control character' => [static fn (): Slot => new Slot("a\nb", 3), 'Slot identifiers are 1 to 255 bytes without control characters.'];
        yield 'delete character' => [static fn (): Slot => new Slot("a\x7F", 3), 'Slot identifiers are 1 to 255 bytes without control characters.'];
        yield 'e-mail applicant' => [static fn (): Applicant => Applicant::person('ana@example.org', []), 'Applicant identifiers are stable opaque identifiers, never e-mail addresses.'];
        yield 'e-mail member' => [static fn (): Applicant => Applicant::group('g', ['bo@mail.example.org'], []), 'Member identifiers are stable opaque identifiers, never e-mail addresses.'];
        yield 'member twice' => [static fn (): Applicant => Applicant::group('g', ['bo', 'bo'], []), 'Applicant "g" names a member twice.'];
        yield 'empty member' => [static fn (): Applicant => Applicant::group('g', ['bo', ''], []), 'Member identifiers are 1 to 255 bytes without control characters.'];
        yield 'empty choice' => [static fn (): Applicant => Applicant::person('a', ['']), 'Slot identifiers are 1 to 255 bytes without control characters.'];
        yield 'choice twice' => [static fn (): Applicant => Applicant::person('a', ['t', 't']), 'Applicant "a" ranks a slot twice.'];
        yield 'pair applicant' => [static fn (): Pair => new Pair('', 't'), 'Applicant identifiers are 1 to 255 bytes without control characters.'];
        yield 'pair slot' => [static fn (): Pair => new Pair('a', ''), 'Slot identifiers are 1 to 255 bytes without control characters.'];
        yield 'zero limit' => [static fn (): Limits => new Limits(members: 0), 'Every limit of a problem is at least 1.'];
        yield 'zero slots' => [static fn (): Limits => new Limits(slots: 0), 'Every limit of a problem is at least 1.'];
        yield 'zero applicants' => [static fn (): Limits => new Limits(applicants: 0), 'Every limit of a problem is at least 1.'];
        yield 'zero choices' => [static fn (): Limits => new Limits(choices: 0), 'Every limit of a problem is at least 1.'];
        yield 'zero budget' => [static fn (): WorkBudget => new WorkBudget(0), 'A work budget is at least one operation.'];
        yield 'empty seed' => [static fn (): Seed => Seed::fromString(''), 'A seed is 1 to 255 bytes.'];
        yield 'long seed' => [static fn (): Seed => Seed::fromString(str_repeat('s', 256)), 'A seed is 1 to 255 bytes.'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidProblem::class);
        $this->expectExceptionMessageIs($message);

        $build();
    }

    public function testIdentifiersAtTheBoundsAndNearAnAddressAreKept(): void
    {
        self::assertSame(str_repeat('a', 255), Identifier::check(str_repeat('a', 255), 'slot'));
        self::assertSame('a', Identifier::check('a', 'slot'));
        self::assertSame('ana@local', Identifier::check('ana@local', 'member'));
        self::assertSame('user:42', Identifier::check('user:42', 'member'));
        self::assertSame("ana@example.org\nx", $this->refusal("ana@example.org\nx"));
    }

    public function testASeedDrawsTheSameOrderEveryTime(): void
    {
        $items = range(1, 20);
        $seed = Seed::fromString('convention-2026');

        self::assertSame('convention-2026', $seed->value);
        self::assertSame($seed->shuffle($items), Seed::fromString('convention-2026')->shuffle($items));
        self::assertNotSame($seed->shuffle($items), Seed::fromString('convention-2027')->shuffle($items));
        self::assertSame([6, 13, 4, 18, 16, 20, 11, 2, 10, 14, 15, 1, 7, 12, 17, 8, 19, 3, 9, 5], $seed->shuffle($items));
        self::assertSame(str_repeat('s', 255), Seed::fromString(str_repeat('s', 255))->value);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', Seed::random()->value);
        self::assertNotSame(Seed::random()->value, Seed::random()->value);
    }

    public function testTheBudgetExceptionNamesItsLimit(): void
    {
        $exception = BudgetExceeded::after(12);

        self::assertInstanceOf(AssignmentException::class, $exception);
        self::assertSame('The assignment stopped after 12 operations, the limit of its work budget.', $exception->getMessage());
    }

    private function refusal(string $id): string
    {
        try {
            Identifier::check($id, 'member');
        } catch (InvalidProblem $invalidProblem) {
            self::assertSame('Member identifiers are 1 to 255 bytes without control characters.', $invalidProblem->getMessage());

            return $id;
        }

        self::fail('A control character is refused before the address check.');
    }
}
