<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Limits;
use Phil\Assignment\ProblemReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProblemReader::class)]
final class ProblemReaderTest extends TestCase
{
    public function testAProblemIsReadFromJson(): void
    {
        $problem = new ProblemReader()->fromJson(<<<'JSON'
            {
              "slots": [{"id": "table-1", "capacity": 5, "minimum": 3}, {"id": "table-2", "capacity": 4}],
              "applicants": [{"id": "ana", "choices": ["table-1"]}, {"id": "g1", "members": ["bo", "cy"], "choices": []}],
              "forbidden": [{"applicant": "ana", "slot": "table-2"}],
              "forced": [{"applicant": "g1", "slot": "table-1"}]
            }
            JSON);

        self::assertSame(3, $problem->slot('table-1')->minimum);
        self::assertSame(0, $problem->slot('table-2')->minimum);
        self::assertSame(['ana'], $problem->applicant('ana')->members);
        self::assertSame(['table-1'], $problem->applicant('ana')->choices);
        self::assertSame(['bo', 'cy'], $problem->applicant('g1')->members);
        self::assertTrue($problem->isForbidden('ana', 'table-2'));
        self::assertSame('table-1', $problem->forcedSlot('g1'));
        self::assertNull($problem->forcedSlot('ana'));
        self::assertSame([], new ProblemReader()->fromArray([])->slots);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'not json' => ['{', 'The problem is not valid JSON.'];
        yield 'too deep' => ['{"slots": [{"id": [[[[[[[1]]]]]]]}]}', 'The problem is nested deeper than its shape allows.'];
        yield 'not an object' => ['"slots"', 'A problem is a JSON object.'];
        yield 'slots not a list' => ['{"slots": {"a": {}}}', '"slots" is a list.'];
        yield 'slots a string' => ['{"slots": "a"}', '"slots" is a list.'];
        yield 'slot not an object' => ['{"slots": [1]}', 'Each item of "slots" is an object.'];
        yield 'id not a string' => ['{"slots": [{"id": 1, "capacity": 1}]}', '"id" is a string.'];
        yield 'id missing' => ['{"slots": [{"capacity": 1}]}', '"id" is a string.'];
        yield 'capacity not an integer' => ['{"slots": [{"id": "a", "capacity": "1"}]}', '"capacity" is an integer.'];
        yield 'capacity missing' => ['{"slots": [{"id": "a"}]}', '"capacity" is an integer.'];
        yield 'minimum not an integer' => ['{"slots": [{"id": "a", "capacity": 1, "minimum": 1.5}]}', 'The minimum of a slot is an integer.'];
        yield 'choices not a list' => ['{"applicants": [{"id": "a", "choices": {"x": "t"}}]}', '"choices" is a list of at most 20 strings.'];
        yield 'choices a string' => ['{"applicants": [{"id": "a", "choices": "t"}]}', '"choices" is a list of at most 20 strings.'];
        yield 'choice not a string' => ['{"applicants": [{"id": "a", "choices": [1]}]}', '"choices" is a list of strings.'];
        yield 'members too many' => ['{"applicants": [{"id": "a", "members": ['.implode(',', array_map(static fn (int $i): string => '"m'.$i.'"', range(1, 51))).']}]}', '"members" is a list of at most 50 strings.'];
        yield 'pair not an object' => ['{"forced": ["a"]}', 'Each item of "forced" is an object.'];
        yield 'pair slot missing' => ['{"forbidden": [{"applicant": "a"}]}', '"slot" is a string.'];
        yield 'pair applicant missing' => ['{"forbidden": [{"slot": "a"}]}', '"applicant" is a string.'];
    }

    #[DataProvider('invalidJson')]
    public function testInvalidInputIsRefused(string $json, string $message): void
    {
        try {
            new ProblemReader()->fromJson($json);
            self::fail($message);
        } catch (InvalidProblem $invalidProblem) {
            self::assertSame($message, $invalidProblem->getMessage());
        }
    }

    public function testListsAreBoundedBeforeAnythingIsBuilt(): void
    {
        $reader = new ProblemReader(new Limits(slots: 1, applicants: 2, choices: 1, members: 1));

        foreach ([
            ['{"slots": [{}, {}]}', '"slots" has at most 1 items.'],
            ['{"applicants": [{}, {}, {}]}', '"applicants" has at most 2 items.'],
            ['{"forbidden": [{}, {}, {}]}', '"forbidden" has at most 2 items.'],
            ['{"applicants": [{"id": "a", "choices": ["x", "y"]}]}', '"choices" is a list of at most 1 strings.'],
        ] as [$json, $message]) {
            try {
                $reader->fromJson($json);
                self::fail($message);
            } catch (InvalidProblem $invalidProblem) {
                self::assertSame($message, $invalidProblem->getMessage());
            }
        }

        self::assertCount(2, $reader->fromJson('{"slots": [{"id": "s", "capacity": 1}], "applicants": [{"id": "a", "choices": ["s"]}, {"id": "b"}], "forbidden": [{"applicant": "a", "slot": "s"}, {"applicant": "b", "slot": "s"}]}')->applicants);
    }

    public function testATextTooLongIsRefusedUnread(): void
    {
        $this->expectException(InvalidProblem::class);
        $this->expectExceptionMessageIs('A problem in JSON is at most 16777216 bytes.');

        new ProblemReader()->fromJson(str_repeat(' ', ProblemReader::MAX_JSON_BYTES + 1));
    }

    public function testATextAtTheLimitIsRead(): void
    {
        self::assertSame([], new ProblemReader()->fromJson('{}'.str_repeat(' ', ProblemReader::MAX_JSON_BYTES - 2))->slots);
    }

    public function testTheNestingAllowedIsEightLevels(): void
    {
        $reader = new ProblemReader();

        self::assertSame([], $reader->fromJson('{"x": [[[[[[0]]]]]]}')->slots);

        try {
            $reader->fromJson('{"x": [[[[[[[0]]]]]]]}');
            self::fail('Nine levels are too deep.');
        } catch (InvalidProblem $invalidProblem) {
            self::assertSame('The problem is nested deeper than its shape allows.', $invalidProblem->getMessage());
        }
    }

    public function testThePairsAreBoundedByTheApplicantsTimesTheirChoices(): void
    {
        $reader = new ProblemReader(new Limits(slots: 1, applicants: 2, choices: 3, members: 1));
        $pairs = implode(',', array_fill(0, 6, '{"applicant": "a", "slot": "s"}'));
        $problem = $reader->fromJson('{"slots": [{"id": "s", "capacity": 1}], "applicants": [{"id": "a"}], "forbidden": ['.$pairs.']}');

        self::assertTrue($problem->isForbidden('a', 's'));

        try {
            $reader->fromJson('{"forbidden": ['.$pairs.', {}]}');
            self::fail('Seven pairs are too many.');
        } catch (InvalidProblem $invalidProblem) {
            self::assertSame('"forbidden" has at most 6 items.', $invalidProblem->getMessage());
        }
    }

    public function testEveryPairOfAListIsRead(): void
    {
        $problem = new ProblemReader()->fromJson('{"slots": [{"id": "s", "capacity": 2}, {"id": "u", "capacity": 2}], "applicants": [{"id": "a"}, {"id": "b"}], "forbidden": [{"applicant": "a", "slot": "s"}, {"applicant": "b", "slot": "u"}], "forced": [{"applicant": "a", "slot": "u"}, {"applicant": "b", "slot": "s"}]}');

        self::assertCount(2, $problem->forbidden);
        self::assertCount(2, $problem->forced);
        self::assertTrue($problem->isForbidden('b', 'u'));
    }
}
