<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\ValueObject\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BasicConditions::class)]
final class BasicConditionsTest extends TestCase
{
    /**
     * @return array<string, Field>
     */
    private function fields(): array
    {
        return [
            'team' => Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green', 'Red']),
            'name' => Field::of('name', 'Name', FieldType::Text),
            'adult' => Field::of('adult', 'Adult', FieldType::Checkbox),
        ];
    }

    public function testAConditionSaysTheFieldItReadsAndWhetherItHolds(): void
    {
        $conditions = new BasicConditions();
        $in = ['in' => [['var' => 'team'], ['Blue', 'Green']]];
        $is = ['==' => [['var' => 'adult'], true]];
        $not = ['!=' => [['var' => 'name'], 'Ada']];

        foreach ([$in, $is, $not] as $condition) {
            $conditions->check($condition, $this->fields());
        }

        self::assertSame([['team'], ['adult'], ['name']], [$conditions->fields($in), $conditions->fields($is), $conditions->fields($not)]);
        self::assertSame(
            [true, true, false, false, true, false, false, true, false, true, true],
            [
                $conditions->holds($in, ['team' => 'Blue']),
                $conditions->holds($in, ['team' => 'Green']),
                $conditions->holds($in, ['team' => 'Red']),
                $conditions->holds($in, []),
                $conditions->holds($is, ['adult' => true]),
                $conditions->holds($is, ['adult' => false]),
                $conditions->holds($is, ['adult' => null]),
                $conditions->holds($not, ['name' => 'Grace']),
                $conditions->holds($not, ['name' => 'Ada']),
                $conditions->holds($not, ['name' => null]),
                $conditions->holds($not, []),
            ],
        );
        self::assertFalse($conditions->holds(['in' => [['var' => 'team'], ['1']]], ['team' => true]), 'compared strictly');
        self::assertSame([false, false], [$conditions->holds(['==' => [['var' => 'adult'], false]], ['adult' => null]), $conditions->holds(['in' => [['var' => 'name'], ['']]], ['name' => null])], 'no value is neither false nor an empty text');
        self::assertTrue($conditions->holds(['!=' => [['var' => 'adult'], true]], ['adult' => null]), 'as JsonLogic: no value is not true');
        self::assertSame(100, BasicConditions::MAX_VALUES);
        $conditions->check(['in' => [['var' => 'name'], array_map(strval(...), range(1, 100))]], $this->fields());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $shape = 'A condition is {"==": …}, {"!=": …} or {"in": …} with two arguments.';
        $variable = 'A condition reads one field, its first argument {"var": "name"}.';

        yield 'empty' => [[], $shape];
        yield 'two operators' => [['==' => [['var' => 'name'], 'a'], '!=' => [['var' => 'name'], 'b']], $shape];
        yield 'other operator' => [['<' => [['var' => 'name'], 'a']], $shape];
        yield 'not a list' => [['==' => ['a' => ['var' => 'name'], 'b' => 'a']], $shape];
        yield 'no arguments' => [['==' => 'name'], $shape];
        yield 'one argument' => [['==' => [['var' => 'name']]], $shape];
        yield 'three arguments' => [['==' => [['var' => 'name'], 'a', 'b']], $shape];
        yield 'no variable' => [['==' => ['name', 'a']], $variable];
        yield 'variable not named' => [['==' => [['var' => 1], 'a']], $variable];
        yield 'variable and more' => [['==' => [['var' => 'name', 'x' => 1], 'a']], $variable];
        yield 'in, nothing' => [['in' => [['var' => 'team'], []]], 'A condition "in" lists 1 to 100 values.'];
        yield 'in, a text' => [['in' => [['var' => 'team'], 'Blue']], 'A condition "in" lists 1 to 100 values.'];
        yield 'in, not a list' => [['in' => [['var' => 'team'], ['a' => 'Blue']]], 'A condition "in" lists 1 to 100 values.'];
        yield 'in, too many' => [['in' => [['var' => 'name'], array_map(strval(...), range(1, 101))]], 'A condition "in" lists 1 to 100 values.'];
        yield 'compared with a number' => [['==' => [['var' => 'name'], 1]], 'A condition compares a field with a text, true or false.'];
        yield 'compared with a list' => [['!=' => [['var' => 'name'], ['a']]], 'A condition compares a field with a text, true or false.'];
        yield 'unknown field' => [['==' => [['var' => 'other'], 'a']], 'A condition reads "other", which is not a field asked before it.'];
        yield 'checkbox in' => [['in' => [['var' => 'adult'], ['1']]], 'A condition on the checkbox "adult" compares it with true or false.'];
        yield 'checkbox with a text' => [['==' => [['var' => 'adult'], 'yes']], 'A condition on the checkbox "adult" compares it with true or false.'];
        yield 'not a choice' => [['in' => [['var' => 'team'], ['Blue', 'Pink']]], 'A condition compares "team" with a value it never takes.'];
        yield 'choice compared with true' => [['==' => [['var' => 'team'], true]], 'A condition compares "team" with a value it never takes.'];
        yield 'text compared with false' => [['!=' => [['var' => 'name'], false]], 'A condition compares "name" with a value it never takes.'];
        yield 'text in numbers' => [['in' => [['var' => 'name'], ['a', 2]]], 'A condition compares "name" with a value it never takes.'];
    }

    /**
     * @param array<array-key, mixed> $condition
     */
    #[DataProvider('refused')]
    public function testAConditionItDoesNotReadIsRefused(array $condition, string $message): void
    {
        $this->expectException(InvalidForm::class);
        $this->expectExceptionMessageIsOrContains($message);

        new BasicConditions()->check($condition, $this->fields());
    }
}
