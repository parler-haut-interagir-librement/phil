<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit;

use Generator;
use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\Evaluation\Values;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\JsonLogic\JsonLogicWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The cases shared with the tests of the Stimulus controller, `tests/Fixtures/cases.json`: the server's evaluator gives
 * the result the browser's does, a rule read back from its canonical form gives it again, and the reader refuses what
 * the browser refuses.
 */
#[CoversClass(Evaluator::class)]
#[CoversClass(Values::class)]
#[CoversClass(JsonLogicReader::class)]
#[CoversClass(JsonLogicWriter::class)]
final class SharedCasesTest extends TestCase
{
    #[DataProvider('evaluated')]
    public function testEachCaseGivesItsResult(mixed $rule, mixed $data, mixed $expected): void
    {
        $reader = new JsonLogicReader();
        $expression = $reader->parse($rule);
        $values = self::toArray($data);
        self::assertIsArray($values);
        $evaluator = new Evaluator();

        self::assertSame($expected, $evaluator->evaluate($expression, $values));
        self::assertSame($expected, $evaluator->evaluate($reader->read(new JsonLogicWriter()->write($expression)), $values));
    }

    #[DataProvider('refused')]
    public function testEachRefusedCaseIsRefused(mixed $rule, string $reason): void
    {
        $this->expectException(InvalidLogic::class);
        new JsonLogicReader()->parse($rule);

        self::fail($reason);
    }

    /**
     * @return Generator<string, array{0: mixed, 1: mixed, 2: mixed}>
     */
    public static function evaluated(): Generator
    {
        $section = '';

        foreach (self::cases()->evaluate as $index => $case) {
            if (\is_string($case)) {
                $section = $case;

                continue;
            }

            self::assertIsArray($case);
            [$rule, $data, $expected] = $case;

            yield \sprintf('%s #%d %s', $section, $index, json_encode($rule, \JSON_THROW_ON_ERROR)) => [$rule, $data, self::toArray($expected)];
        }
    }

    /**
     * @return Generator<string, array{0: mixed, 1: string}>
     */
    public static function refused(): Generator
    {
        foreach (self::cases()->refuse as $index => $case) {
            self::assertIsArray($case);
            [$rule, $reason] = $case;
            self::assertIsString($reason);

            yield \sprintf('#%d %s', $index, $reason) => [$rule, $reason];
        }
    }

    /**
     * @return object{evaluate: list<mixed>, refuse: list<array{0: mixed, 1: string}>}
     */
    private static function cases(): object
    {
        $json = file_get_contents(\dirname(__DIR__).'/Fixtures/cases.json');
        self::assertIsString($json);
        $cases = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        self::assertIsObject($cases);

        /** @var object{evaluate: list<mixed>, refuse: list<array{0: mixed, 1: string}>} $cases */
        return $cases;
    }

    /**
     * The data and the results as PHP holds them: objects as maps.
     */
    private static function toArray(mixed $value): mixed
    {
        if (\is_object($value)) {
            $value = get_object_vars($value);
        }

        return \is_array($value) ? array_map(self::toArray(...), $value) : $value;
    }
}
