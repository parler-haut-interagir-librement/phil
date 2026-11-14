<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit;

use Generator;
use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\Evaluation\Values;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The test cases JsonLogic publishes, `tests/Fixtures/json-logic/tests.json`, kept unchanged with their licence: each
 * rule of the subset gives the value JsonLogic expects, and each rule the reader refuses is one that
 * `differences.json` lists with its reason. The differences on values that no published case reaches are listed there
 * too, each with the value the library gives. `tests/js/official.test.mjs` runs the same files through the browser's
 * evaluator.
 */
#[CoversClass(Evaluator::class)]
#[CoversClass(Values::class)]
#[CoversClass(JsonLogicReader::class)]
final class OfficialCasesTest extends TestCase
{
    private const int FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION;

    #[DataProvider('published')]
    public function testEachPublishedCaseGivesItsValueOrIsAListedRefusal(mixed $rule, mixed $data, mixed $expected): void
    {
        $refused = $this->refusedRules();
        $listed = isset($refused[json_encode($rule, self::FLAGS)]);

        try {
            $expression = new JsonLogicReader()->parse($rule);
        } catch (InvalidLogic $invalidLogic) {
            self::assertTrue($listed, 'Refused without being listed in differences.json: '.$invalidLogic->getMessage());

            return;
        }

        self::assertFalse($listed, 'Listed as refused in differences.json, but read.');
        // An answer of `null` is no answer.
        $answer = self::toArray($data) ?? [];
        self::assertIsArray($answer);

        self::assertSame(self::toArray($expected), new Evaluator()->evaluate($expression, $answer));
    }

    public function testEachListedRefusalIsAPublishedCase(): void
    {
        $published = [];

        foreach (self::published() as [$rule]) {
            $published[json_encode($rule, self::FLAGS)] = true;
        }

        $refused = $this->refusedRules();
        self::assertCount(71, $refused);
        self::assertSame([], array_keys(array_diff_key($refused, $published)), 'Listed in differences.json, but in no published case.');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('differences')]
    public function testEachDifferenceOnValuesGivesTheListedValue(mixed $rule, array $data, mixed $value, string $reason): void
    {
        self::assertSame($value, new Evaluator()->evaluate(new JsonLogicReader()->parse($rule), $data), $reason);
    }

    public function testThePublishedFileIsTheOneItsLicenceNames(): void
    {
        $source = self::differencesFile()->source ?? null;
        self::assertIsObject($source);
        $file = \dirname(__DIR__).'/Fixtures/json-logic/tests.json';

        self::assertSame($source->sha256 ?? null, hash_file('sha256', $file));
        self::assertStringContainsString((string) ($source->sha256 ?? ''), (string) file_get_contents(\dirname($file).'/LICENSE'));
    }

    /**
     * @return Generator<string, array{0: mixed, 1: mixed, 2: mixed}>
     */
    public static function published(): Generator
    {
        $json = file_get_contents(\dirname(__DIR__).'/Fixtures/json-logic/tests.json');
        self::assertIsString($json);
        $cases = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);
        $section = '';

        foreach ($cases as $index => $case) {
            if (\is_string($case)) {
                $section = $case;

                continue;
            }

            self::assertIsArray($case);
            [$rule, $data, $expected] = $case;

            yield \sprintf('%s #%s %s', $section, $index, json_encode($rule, self::FLAGS)) => [$rule, $data, $expected];
        }
    }

    /**
     * @return Generator<string, array{0: mixed, 1: array<array-key, mixed>, 2: mixed, 3: string}>
     */
    public static function differences(): Generator
    {
        $values = self::differencesFile()->values ?? null;
        self::assertIsArray($values);

        foreach ($values as $difference) {
            self::assertIsObject($difference);
            $data = self::toArray($difference->data ?? null);
            self::assertIsArray($data);
            self::assertIsString($difference->reason ?? null);

            yield json_encode($difference->rule ?? null, self::FLAGS) => [$difference->rule ?? null, $data, self::toArray($difference->value ?? null), $difference->reason];
        }
    }

    /**
     * The rules listed as refused, each written as compact JSON, as keys.
     *
     * @return array<string, true>
     */
    private function refusedRules(): array
    {
        $groups = self::differencesFile()->refused ?? null;
        self::assertIsArray($groups);
        $rules = [];

        foreach ($groups as $group) {
            self::assertIsObject($group);
            self::assertIsString($group->reason ?? null);
            self::assertIsArray($group->rules ?? null);

            foreach ($group->rules as $rule) {
                $rules[json_encode($rule, self::FLAGS)] = true;
            }
        }

        return $rules;
    }

    private static function differencesFile(): object
    {
        $json = file_get_contents(\dirname(__DIR__).'/Fixtures/json-logic/differences.json');
        self::assertIsString($json);
        $differences = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        self::assertIsObject($differences);

        return $differences;
    }

    /**
     * The data and the values as PHP holds them: objects as maps.
     */
    private static function toArray(mixed $value): mixed
    {
        if (\is_object($value)) {
            $value = get_object_vars($value);
        }

        return \is_array($value) ? array_map(self::toArray(...), $value) : $value;
    }
}
