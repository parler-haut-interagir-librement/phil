<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\ActivityStatus;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\ObjectiveStatus;
use Phil\ElearningPackages\Sequencing\SequencingState;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequencingState::class)]
#[CoversClass(ActivityStatus::class)]
#[CoversClass(ObjectiveStatus::class)]
#[CoversClass(SequencingRefused::class)]
final class SequencingStateTest extends TestCase
{
    private function played(): M
    {
        $map = M::sequencing('<imsss:objectives><imsss:primaryObjective objectiveID="p"><imsss:mapInfo targetObjectiveID="urn:g" writeSatisfiedStatus="true" writeNormalizedMeasure="true"/></imsss:primaryObjective></imsss:objectives>');
        $session = M::session(M::item('m', M::flow('<imsss:randomizationControls randomizationTiming="once" reorderChildren="true"/>'), M::item('s1', $map).M::item('s2')).M::item('s3'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(false, true, 1.0, Duration::ofCentiseconds(1_234), [], 'incomplete'));

        return $session;
    }

    public function testAStateGoesToPlainValuesAndBack(): void
    {
        $session = $this->played();
        $data = $session->state->toArray();
        $json = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        $state = SequencingState::fromArray($json, $session->tree);

        self::assertEquals($session->state, $state);
        self::assertSame(1, $data['version']);
        self::assertSame(42, $data['seed']);
        self::assertSame(42, $state->seed);
        self::assertSame($session->state->current, $state->current);
        self::assertNull($state->suspendedActivity);
        self::assertSame(['org', 'm', $state->current], array_keys($data['activities']));
        self::assertSame(['attempts', 'active', 'suspended', 'completed', 'duration', 'lesson', 'objectives', 'order'], array_keys($data['activities']['m']));
        self::assertSame(['urn:g' => ['satisfied' => true, 'measure' => 1.0]], $data['globals']);
    }

    public function testAnActivityIsReadAsItsStatus(): void
    {
        $session = $this->played();
        $current = (string) $session->state->current;
        $status = $session->state->activity($current);

        self::assertSame(1, $status->attempts);
        self::assertTrue($status->active);
        self::assertFalse($status->suspended);
        self::assertFalse($status->completed);
        self::assertEquals(Duration::ofCentiseconds(1_234), $status->duration);
        self::assertSame('incomplete', $status->lessonStatus);
        self::assertEquals(new ActivityStatus(), $session->state->activity('s3'));
        self::assertEquals(new ObjectiveStatus(), $session->state->activity('s3')->objective('#primary'));
        self::assertEquals(new ObjectiveStatus(), $session->state->global('urn:none'));
        self::assertEquals(['urn:g' => new ObjectiveStatus(true, 1.0)], $session->state->globals());
        self::assertNull($session->state->activity('m')->duration);
    }

    public function testABeginningDrawsASeed(): void
    {
        self::assertGreaterThanOrEqual(0, SequencingState::begin()->seed);
        self::assertSame(7, SequencingState::begin(7)->seed);
        self::assertSame(['version' => 1, 'seed' => 7, 'current' => null, 'suspended' => null, 'activities' => [], 'globals' => []], SequencingState::begin(7)->toArray());
    }

    public function testAnIntegerMeasureReadsAsADecimal(): void
    {
        $tree = $this->played()->tree;
        $state = SequencingState::fromArray(['version' => 1, 'seed' => 1, 'activities' => ['s1' => ['attempts' => 0, 'active' => false, 'suspended' => false, 'objectives' => ['p' => ['measure' => 1]]]], 'globals' => ['g' => ['satisfied' => false, 'measure' => -1]]], $tree);

        self::assertSame(1.0, $state->activity('s1')->objective('p')->measure);
        self::assertNull($state->activity('s1')->objective('p')->satisfied);
        self::assertNull($state->activity('s1')->completed);
        self::assertSame(-1.0, $state->global('g')->measure);
        self::assertNull($state->current);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalid(): iterable
    {
        $record = ['attempts' => 1, 'active' => false, 'suspended' => false, 'completed' => null, 'duration' => null, 'lesson' => null, 'objectives' => [], 'order' => null];
        $state = static fn (array $activities = [], array $globals = [], array $more = []): array => $more + ['version' => 1, 'seed' => 1, 'current' => null, 'suspended' => null, 'activities' => $activities, 'globals' => $globals];

        yield 'another version' => [['version' => 2, 'seed' => 1] + $state()];
        yield 'no seed' => [['seed' => '1'] + $state()];
        yield 'no activities' => [['activities' => 'none'] + $state()];
        yield 'no globals' => [['globals' => null] + $state()];
        yield 'too many globals' => [$state([], array_fill(0, SequencingState::MAX_GLOBALS + 1, ['satisfied' => null, 'measure' => null]))];
        yield 'unknown current' => [$state([], [], ['current' => 'nowhere'])];
        yield 'current not text' => [$state([], [], ['current' => 3])];
        yield 'unknown suspended' => [$state([], [], ['suspended' => 'nowhere'])];
        yield 'unknown activity' => [$state(['nowhere' => $record])];
        yield 'activity not a record' => [$state(['s1' => true])];
        yield 'negative attempts' => [$state(['s1' => ['attempts' => -1] + $record])];
        yield 'too many attempts' => [$state(['s1' => ['attempts' => 1_000_000_001] + $record])];
        yield 'attempts as text' => [$state(['s1' => ['attempts' => '1'] + $record])];
        yield 'active not a boolean' => [$state(['s1' => ['active' => 1] + $record])];
        yield 'suspended missing' => [$state(['s1' => array_diff_key($record, ['suspended' => true])])];
        yield 'completed not a boolean' => [$state(['s1' => ['completed' => 'yes'] + $record])];
        yield 'negative duration' => [$state(['s1' => ['duration' => -1] + $record])];
        yield 'duration too long' => [$state(['s1' => ['duration' => Duration::MAX_CENTISECONDS + 1] + $record])];
        yield 'duration as text' => [$state(['s1' => ['duration' => '10'] + $record])];
        yield 'unknown lesson status' => [$state(['s1' => ['lesson' => 'done'] + $record])];
        yield 'objectives not a list' => [$state(['s1' => ['objectives' => 'p'] + $record])];
        yield 'too many objectives' => [$state(['s1' => ['objectives' => array_fill(0, 65, ['satisfied' => true])] + $record])];
        yield 'objective not a record' => [$state(['s1' => ['objectives' => ['p' => 1]] + $record])];
        yield 'satisfied not a boolean' => [$state(['s1' => ['objectives' => ['p' => ['satisfied' => 'yes']]] + $record])];
        yield 'measure above one' => [$state([], ['g' => ['measure' => 1.5]])];
        yield 'measure below minus one' => [$state([], ['g' => ['measure' => -1.01]])];
        yield 'measure as text' => [$state([], ['g' => ['measure' => '0.5']])];
        yield 'order not a list' => [$state(['m' => ['order' => 's1'] + $record])];
        yield 'order with keys' => [$state(['m' => ['order' => ['a' => 's1']] + $record])];
        yield 'order of others' => [$state(['m' => ['order' => ['s3']] + $record])];
        yield 'order twice' => [$state(['m' => ['order' => ['s1', 's1']] + $record])];
        yield 'order of integers' => [$state(['m' => ['order' => [1]] + $record])];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('invalid')]
    public function testAStateOutOfItsBoundsIsRefused(array $data): void
    {
        $this->expectException(SequencingRefused::class);

        SequencingState::fromArray($data, $this->played()->tree);
    }

    public function testTheBoundsThemselvesAreRead(): void
    {
        $record = ['attempts' => 1_000_000_000, 'active' => true, 'suspended' => true, 'completed' => true, 'duration' => Duration::MAX_CENTISECONDS, 'lesson' => 'not attempted', 'objectives' => array_fill(0, 64, ['satisfied' => false, 'measure' => -1.0]), 'order' => ['s2', 's1']];
        $state = SequencingState::fromArray(['version' => 1, 'seed' => 1, 'current' => 's1', 'suspended' => 's2', 'activities' => ['m' => $record, 's1' => ['duration' => 0, 'order' => []] + $record], 'globals' => array_fill(0, SequencingState::MAX_GLOBALS, ['satisfied' => true, 'measure' => 1.0])], $this->played()->tree);

        self::assertSame(['s2', 's1'], $state->toArray()['activities']['m']['order']);
        self::assertSame(0, $state->toArray()['activities']['s1']['duration']);
        self::assertCount(64, $state->activity('m')->objectives);
        self::assertCount(SequencingState::MAX_GLOBALS, $state->globals());
        self::assertSame('s1', $state->current);
        self::assertSame('s2', $state->suspendedActivity);
        self::assertTrue($state->activity('m')->completed);
    }

    public function testMessagesSayWhatIsWrong(): void
    {
        $tree = $this->played()->tree;

        foreach ([
            'A sequencing state is of version 1 and holds an integer seed.' => ['version' => 1],
            'A sequencing state holds its activities and at most 10000 global objectives.' => ['version' => 1, 'seed' => 1],
            'A sequencing state names an activity the tree does not hold.' => ['version' => 1, 'seed' => 1, 'activities' => [], 'globals' => [], 'current' => 'x'],
            'The state of the activity "s1" holds a value out of its bounds.' => ['version' => 1, 'seed' => 1, 'activities' => ['s1' => []], 'globals' => []],
            'The state of the activity "m" orders children it does not have.' => ['version' => 1, 'seed' => 1, 'activities' => ['m' => ['attempts' => 0, 'active' => false, 'suspended' => false, 'objectives' => [], 'order' => ['x']]], 'globals' => []],
            'An objective of a sequencing state holds a value out of its bounds.' => ['version' => 1, 'seed' => 1, 'activities' => [], 'globals' => ['g' => 'x']],
        ] as $message => $data) {
            try {
                SequencingState::fromArray($data, $tree);
                self::fail('The state is refused: '.$message);
            } catch (SequencingRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }
}
