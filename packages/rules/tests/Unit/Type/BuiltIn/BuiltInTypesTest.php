<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Type\BuiltIn;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Rule;
use Phil\Rules\Type\BuiltIn\DateCompare;
use Phil\Rules\Type\BuiltIn\FactCompare;
use Phil\Rules\Type\BuiltIn\FactContains;
use Phil\Rules\Type\BuiltIn\FactEquals;
use Phil\Rules\Type\BuiltIn\FactIn;
use Phil\Rules\Type\BuiltIn\FactPresent;
use Phil\Rules\Type\BuiltIn\Read;
use Phil\Rules\Type\ConditionTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FactEquals::class)]
#[CoversClass(FactIn::class)]
#[CoversClass(FactContains::class)]
#[CoversClass(FactCompare::class)]
#[CoversClass(FactPresent::class)]
#[CoversClass(DateCompare::class)]
#[CoversClass(Read::class)]
#[CoversClass(Verdict::class)]
final class BuiltInTypesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>, array<string, mixed>, bool, string, array<string, scalar|null>}>
     */
    public static function cases(): iterable
    {
        $paris = new DateTimeImmutable('2026-12-01 00:30', new DateTimeZone('Europe/Paris'));

        yield 'equals a text' => ['fact.equals', ['fact' => 'country', 'value' => 'CA'], ['country' => 'CA'], true, 'fact.equal', ['fact' => 'country']];
        yield 'differs from a text' => ['fact.equals', ['fact' => 'country', 'value' => 'CA'], ['country' => 'ca'], false, 'fact.different', ['fact' => 'country']];
        yield 'equals a number of another type' => ['fact.equals', ['fact' => 'n', 'value' => 2], ['n' => 2.0], true, 'fact.equal', ['fact' => 'n']];
        yield 'equals a float to an integer' => ['fact.equals', ['fact' => 'n', 'value' => 2.0], ['n' => 2], true, 'fact.equal', ['fact' => 'n']];
        yield 'differs between numbers' => ['fact.equals', ['fact' => 'n', 'value' => 2], ['n' => 3], false, 'fact.different', ['fact' => 'n']];
        yield 'differs between large integers' => ['fact.equals', ['fact' => 'n', 'value' => \PHP_INT_MAX], ['n' => \PHP_INT_MAX - 1], false, 'fact.different', ['fact' => 'n']];
        yield 'a number differs from its text' => ['fact.equals', ['fact' => 'n', 'value' => 2], ['n' => '2'], false, 'fact.different', ['fact' => 'n']];
        yield 'a boolean differs from a number' => ['fact.equals', ['fact' => 'n', 'value' => true], ['n' => 1], false, 'fact.different', ['fact' => 'n']];
        yield 'a missing fact' => ['fact.equals', ['fact' => 'country', 'value' => 'CA'], [], false, 'fact.missing', ['fact' => 'country']];
        yield 'in the list' => ['fact.in', ['fact' => 'tier', 'values' => ['gold', 'silver']], ['tier' => 'silver'], true, 'fact.listed', ['fact' => 'tier']];
        yield 'in the list by number' => ['fact.in', ['fact' => 'n', 'values' => [1, 2]], ['n' => 2.0], true, 'fact.listed', ['fact' => 'n']];
        yield 'out of the list' => ['fact.in', ['fact' => 'tier', 'values' => ['gold']], ['tier' => 'bronze'], false, 'fact.unlisted', ['fact' => 'tier']];
        yield 'in the list but missing' => ['fact.in', ['fact' => 'tier', 'values' => ['gold']], [], false, 'fact.missing', ['fact' => 'tier']];
        yield 'contains' => ['fact.contains', ['fact' => 'groups', 'value' => 'staff'], ['groups' => ['crew', 'staff']], true, 'fact.contained', ['fact' => 'groups']];
        yield 'does not contain' => ['fact.contains', ['fact' => 'groups', 'value' => 'staff'], ['groups' => ['crew']], false, 'fact.not-contained', ['fact' => 'groups']];
        yield 'contains but not a list' => ['fact.contains', ['fact' => 'groups', 'value' => 'staff'], ['groups' => 'staff'], false, 'fact.type', ['fact' => 'groups', 'expected' => 'list']];
        yield 'contains but missing' => ['fact.contains', ['fact' => 'groups', 'value' => 'staff'], [], false, 'fact.missing', ['fact' => 'groups']];
        yield 'below' => ['fact.compare', ['fact' => 'n', 'operator' => 'lt', 'value' => 3], ['n' => 2], true, 'fact.compared', ['fact' => 'n', 'operator' => 'lt']];
        yield 'not below at the value' => ['fact.compare', ['fact' => 'n', 'operator' => 'lt', 'value' => 3], ['n' => 3], false, 'fact.out-of-range', ['fact' => 'n', 'operator' => 'lt']];
        yield 'at most at the value' => ['fact.compare', ['fact' => 'n', 'operator' => 'lte', 'value' => 3], ['n' => 3], true, 'fact.compared', ['fact' => 'n', 'operator' => 'lte']];
        yield 'not at most above' => ['fact.compare', ['fact' => 'n', 'operator' => 'lte', 'value' => 3], ['n' => 3.5], false, 'fact.out-of-range', ['fact' => 'n', 'operator' => 'lte']];
        yield 'above' => ['fact.compare', ['fact' => 'n', 'operator' => 'gt', 'value' => 3], ['n' => 3.5], true, 'fact.compared', ['fact' => 'n', 'operator' => 'gt']];
        yield 'not above at the value' => ['fact.compare', ['fact' => 'n', 'operator' => 'gt', 'value' => 3], ['n' => 3], false, 'fact.out-of-range', ['fact' => 'n', 'operator' => 'gt']];
        yield 'at least at the value' => ['fact.compare', ['fact' => 'n', 'operator' => 'gte', 'value' => 3], ['n' => 3], true, 'fact.compared', ['fact' => 'n', 'operator' => 'gte']];
        yield 'not at least below' => ['fact.compare', ['fact' => 'n', 'operator' => 'gte', 'value' => 3], ['n' => 2], false, 'fact.out-of-range', ['fact' => 'n', 'operator' => 'gte']];
        yield 'compare a text' => ['fact.compare', ['fact' => 'n', 'operator' => 'gte', 'value' => 3], ['n' => '4'], false, 'fact.type', ['fact' => 'n', 'expected' => 'number']];
        yield 'compare a missing fact' => ['fact.compare', ['fact' => 'n', 'operator' => 'gte', 'value' => 3], [], false, 'fact.missing', ['fact' => 'n']];
        yield 'present' => ['fact.present', ['fact' => 'email'], ['email' => ''], true, 'fact.present', ['fact' => 'email']];
        yield 'null' => ['fact.present', ['fact' => 'email'], ['email' => null], false, 'fact.null', ['fact' => 'email']];
        yield 'absent' => ['fact.present', ['fact' => 'email'], [], false, 'fact.missing', ['fact' => 'email']];
        yield 'before' => ['date.compare', ['fact' => 'joined', 'operator' => 'before', 'date' => '2026-12-01'], ['joined' => new DateTimeImmutable('2026-11-30 23:59')], true, 'date.compared', ['fact' => 'joined', 'operator' => 'before']];
        yield 'not before on the day' => ['date.compare', ['fact' => 'joined', 'operator' => 'before', 'date' => '2026-12-01'], ['joined' => new DateTimeImmutable('2026-12-01 00:00')], false, 'date.out-of-range', ['fact' => 'joined', 'operator' => 'before']];
        yield 'after' => ['date.compare', ['fact' => 'joined', 'operator' => 'after', 'date' => '2026-12-01'], ['joined' => new DateTimeImmutable('2026-12-02')], true, 'date.compared', ['fact' => 'joined', 'operator' => 'after']];
        yield 'not after on the day' => ['date.compare', ['fact' => 'joined', 'operator' => 'after', 'date' => '2026-12-01'], ['joined' => new DateTimeImmutable('2026-12-01 23:59')], false, 'date.out-of-range', ['fact' => 'joined', 'operator' => 'after']];
        yield 'on or before on the day' => ['date.compare', ['fact' => 'joined', 'operator' => 'on-or-before', 'date' => '2026-12-01'], ['joined' => new DateTimeImmutable('2026-12-01 23:59')], true, 'date.compared', ['fact' => 'joined', 'operator' => 'on-or-before']];
        yield 'not on or before after' => ['date.compare', ['fact' => 'joined', 'operator' => 'on-or-before', 'date' => '2026-12-01'], ['joined' => new DateTimeImmutable('2026-12-02')], false, 'date.out-of-range', ['fact' => 'joined', 'operator' => 'on-or-before']];
        yield 'on or after on the day' => ['date.compare', ['fact' => 'joined', 'operator' => 'on-or-after', 'date' => '2026-12-01'], ['joined' => $paris], true, 'date.compared', ['fact' => 'joined', 'operator' => 'on-or-after']];
        yield 'not on or after before' => ['date.compare', ['fact' => 'joined', 'operator' => 'on-or-after', 'date' => '2026-12-01'], ['joined' => $paris->setTimezone(new DateTimeZone('UTC'))], false, 'date.out-of-range', ['fact' => 'joined', 'operator' => 'on-or-after']];
        yield 'date of a text' => ['date.compare', ['fact' => 'joined', 'operator' => 'after', 'date' => '2026-12-01'], ['joined' => '2026-12-05'], false, 'fact.type', ['fact' => 'joined', 'expected' => 'date']];
        yield 'date missing' => ['date.compare', ['fact' => 'joined', 'operator' => 'after', 'date' => '2026-12-01'], [], false, 'fact.missing', ['fact' => 'joined']];
    }

    /**
     * @param array<string, mixed>       $operands
     * @param array<string, mixed>       $facts
     * @param array<string, scalar|null> $details
     */
    #[DataProvider('cases')]
    public function testEvaluates(string $type, array $operands, array $facts, bool $passed, string $reason, array $details): void
    {
        $result = new Rule(new Leaf($type, $operands), ConditionTypes::builtIn())->evaluate(Facts::of($facts));

        self::assertSame($passed, $result->passed);
        self::assertSame($reason, $result->trace->reason);
        self::assertSame($details, $result->trace->details);
    }

    public function testVerdictsKeepTheirParts(): void
    {
        self::assertEquals(new Verdict(true, 'r', ['a' => 1]), Verdict::pass('r', ['a' => 1]));
        self::assertEquals(new Verdict(false, 'r', []), Verdict::fail('r'));
    }
}
