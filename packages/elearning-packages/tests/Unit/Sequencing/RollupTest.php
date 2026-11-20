<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Sequencing\Activity;
use Phil\ElearningPackages\Sequencing\ActivityTree;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\ControlMode;
use Phil\ElearningPackages\Sequencing\DeliveryControls;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\ObjectiveStatus;
use Phil\ElearningPackages\Sequencing\RollupControls;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\Sequencing;
use Phil\ElearningPackages\Sequencing\SequencingAttempts;
use Phil\ElearningPackages\Sequencing\SequencingData;
use Phil\ElearningPackages\Sequencing\SequencingFlow;
use Phil\ElearningPackages\Sequencing\SequencingRollup;
use Phil\ElearningPackages\Sequencing\SequencingRules;
use Phil\ElearningPackages\Sequencing\SequencingRun;
use Phil\ElearningPackages\Sequencing\SequencingState;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(Sequencer::class)]
#[CoversClass(SequencingRun::class)]
#[CoversClass(SequencingData::class)]
#[CoversClass(SequencingFlow::class)]
#[CoversClass(SequencingRules::class)]
#[CoversClass(SequencingRollup::class)]
#[CoversClass(SequencingAttempts::class)]
final class RollupTest extends TestCase
{
    /**
     * Delivers each child of the root in turn by choice and reports its result, then returns the session.
     *
     * @param array<string, AttemptResult> $results
     */
    private function walk(M $session, array $results): M
    {
        foreach ($results as $activity => $result) {
            $session->go(NavigationRequest::Choice, $activity);
            $session->report($result);
        }

        $session->go(NavigationRequest::Exit);

        return $session;
    }

    private function children(string $rollup, string $childSequencing = '', int $count = 3): M
    {
        $items = '';

        for ($index = 1; $index <= $count; ++$index) {
            $items .= M::item('s'.$index, $childSequencing);
        }

        return M::session(M::item('m', M::flow($rollup), $items));
    }

    public function testByDefaultAClusterIsSatisfiedAndCompletedWhenAllItsChildrenAre(): void
    {
        $session = $this->walk($this->children(''), ['s1' => new AttemptResult(true, true), 's2' => new AttemptResult(true, true), 's3' => new AttemptResult(true, true)]);

        self::assertTrue($session->state->activity('m')->completed);
        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
        self::assertTrue($session->state->activity('org')->completed);
    }

    public function testByDefaultOneFailureMakesTheClusterNotSatisfiedOnceAllAreKnown(): void
    {
        $session = $this->walk($this->children('<imsss:deliveryControls objectiveSetByContent="true" completionSetByContent="true"/>'), ['s1' => new AttemptResult(true, true), 's2' => new AttemptResult(false, false)]);

        self::assertNull($session->state->activity('m')->completed);
        self::assertNull($session->state->activity('m')->objective('#primary')->satisfied);

        $session = $this->walk($session, ['s3' => new AttemptResult(true, true)]);
        self::assertFalse($session->state->activity('m')->completed);
        self::assertFalse($session->state->activity('m')->objective('#primary')->satisfied);
    }

    /**
     * @return iterable<string, array{string, list<?bool>, ?bool}>
     */
    public static function childActivitySets(): iterable
    {
        $satisfied = M::rollupCondition('satisfied');
        yield 'any, one' => [M::rollupRule('satisfied', $satisfied, 'any'), [true, false, null], true];
        yield 'any, none' => [M::rollupRule('satisfied', $satisfied, 'any'), [false, false, null], null];
        yield 'all, all' => [M::rollupRule('satisfied', $satisfied, 'all'), [true, true, true], true];
        yield 'all, one unknown' => [M::rollupRule('satisfied', $satisfied, 'all'), [true, true, null], null];
        yield 'none, none' => [M::rollupRule('notSatisfied', $satisfied, 'none'), [false, false, false], false];
        yield 'none, one unknown' => [M::rollupRule('notSatisfied', $satisfied, 'none'), [false, false, null], null];
        yield 'none, one' => [M::rollupRule('notSatisfied', $satisfied, 'none'), [false, true, false], null];
        yield 'at least 2 of 3' => [M::rollupRule('satisfied', $satisfied, 'atLeastCount', 'minimumCount="2"'), [true, false, true], true];
        yield 'at least 2, one' => [M::rollupRule('satisfied', $satisfied, 'atLeastCount', 'minimumCount="2"'), [true, false, false], null];
        yield 'at least half, two thirds' => [M::rollupRule('satisfied', $satisfied, 'atLeastPercent', 'minimumPercent="0.5"'), [true, true, false], true];
        yield 'at least two thirds, exactly' => [M::rollupRule('satisfied', $satisfied, 'atLeastPercent', 'minimumPercent="0.66"'), [true, true, false], true];
        yield 'at least 0.7, two thirds' => [M::rollupRule('satisfied', $satisfied, 'atLeastPercent', 'minimumPercent="0.7"'), [true, true, false], null];
        yield 'not satisfied, any' => [M::rollupRule('notSatisfied', M::rollupCondition('satisfied', true), 'any'), [true, false, true], false];
        yield 'not of unknown stays unknown' => [M::rollupRule('notSatisfied', M::rollupCondition('satisfied', true), 'any'), [true, null, true], null];
        yield 'all conditions' => [M::rollupRule('satisfied', $satisfied.M::rollupCondition('completed'), 'all', '', 'all'), [true, true, true], true];
        yield 'all conditions, one unknown' => [M::rollupRule('satisfied', $satisfied.M::rollupCondition('objectiveMeasureKnown'), 'all', '', 'all'), [true, true, true], null];
    }

    /**
     * @param list<?bool> $children
     */
    #[DataProvider('childActivitySets')]
    public function testRollupRulesCountTheChildrenThatMeetThem(string $rule, array $children, ?bool $satisfied): void
    {
        $session = $this->children('<imsss:rollupRules>'.$rule.'</imsss:rollupRules>', M::sequencing('<imsss:deliveryControls objectiveSetByContent="true"/>'));
        $results = [];

        foreach ($children as $index => $child) {
            $results['s'.($index + 1)] = new AttemptResult(true, $child);
        }

        self::assertSame($satisfied, $this->walk($session, $results)->state->activity('m')->objective('#primary')->satisfied);
    }

    public function testCompletionRulesAreTheirOwn(): void
    {
        $rules = '<imsss:rollupRules>'.M::rollupRule('completed', M::rollupCondition('completed'), 'any').M::rollupRule('incomplete', M::rollupCondition('attempted'), 'any').'</imsss:rollupRules>';
        $session = $this->walk($this->children($rules, M::sequencing('<imsss:deliveryControls completionSetByContent="true"/>')), ['s1' => new AttemptResult(false)]);

        self::assertFalse($session->state->activity('m')->completed);
        self::assertSame('s2', $session->go(NavigationRequest::Choice, 's2'));
        $session->report(new AttemptResult(true));
        self::assertTrue($session->state->activity('m')->completed);
        // The objective keeps its defaults: the children are not all satisfied.
        self::assertNull($session->state->activity('m')->objective('#primary')->satisfied);
    }

    public function testAChildLeftOutOfTheRollupDoesNotCount(): void
    {
        $out = M::sequencing('<imsss:rollupRules rollupObjectiveSatisfied="false" rollupProgressCompletion="false"/><imsss:deliveryControls objectiveSetByContent="true" completionSetByContent="true"/>');
        $session = M::session(M::item('m', M::flow(), M::item('s1').M::item('s2', $out).M::item('s3', M::sequencing('<imsss:deliveryControls tracked="false"/>'))));
        $session = $this->walk($session, ['s1' => new AttemptResult(true, true), 's2' => new AttemptResult(false, false)]);

        self::assertTrue($session->state->activity('m')->completed);
        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
        self::assertNull($session->state->activity('s3')->completed);
    }

    public function testAnUntrackedLeafEndsWithoutData(): void
    {
        $session = M::session(M::item('s1', M::sequencing('<imsss:deliveryControls tracked="false"/>')).M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);

        self::assertNull($session->state->activity('s1')->completed);
        self::assertSame([], $session->state->activity('s1')->objectives);
    }

    /**
     * @return iterable<string, array{string, ?bool}>
     */
    public static function considerations(): iterable
    {
        yield 'always' => ['requiredForSatisfied="always"', null];
        yield 'if attempted' => ['requiredForSatisfied="ifAttempted"', true];
        yield 'if not skipped' => ['requiredForSatisfied="ifNotSkipped"', true];
        yield 'if not suspended' => ['requiredForSatisfied="ifNotSuspended"', null];
    }

    #[DataProvider('considerations')]
    public function testRollupConsiderationsLeaveChildrenOut(string $consideration, ?bool $satisfied): void
    {
        $considered = M::sequencing('<adlseq:rollupConsiderations '.$consideration.'/>'.M::rules('pre', [['skip', M::condition('attempted', true)]]));
        $session = M::session(M::item('m', M::flow(), M::item('s1').M::item('s2', $considered)));
        $session->go(NavigationRequest::Choice, 's1');
        $session->report(new AttemptResult(true, true));

        self::assertSame($satisfied, $session->state->activity('m')->objective('#primary')->satisfied);
    }

    public function testASuspendedChildIsLeftOutWhenTheConsiderationsSaySo(): void
    {
        $considered = M::sequencing('<adlseq:rollupConsiderations requiredForSatisfied="ifNotSuspended" requiredForNotSatisfied="ifNotSuspended" requiredForCompleted="ifNotSuspended" requiredForIncomplete="ifNotSuspended"/>');
        $session = M::session(M::item('m', M::flow(), M::item('s1').M::item('s2', $considered)));
        $session->go(NavigationRequest::Choice, 's2');
        $session->report(new AttemptResult(false, suspend: true));
        $session->go(NavigationRequest::Choice, 's1');
        $session->report(new AttemptResult(true, true));

        self::assertTrue($session->state->activity('s2')->suspended);
        self::assertFalse($session->state->activity('s2')->completed);
        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
        self::assertTrue($session->state->activity('m')->completed);

        self::assertSame('s2', $session->go(NavigationRequest::Choice, 's2'));
        self::assertSame(1, $session->state->activity('s2')->attempts);
        self::assertFalse($session->state->activity('s2')->suspended);
    }

    public function testADisabledChildDoesNotCount(): void
    {
        $disabled = M::sequencing(M::rules('pre', [['disabled', M::condition('always')]]));
        $session = $this->walk(M::session(M::item('m', M::flow(), M::item('s1').M::item('s2', $disabled))), ['s1' => new AttemptResult(true, true)]);

        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
    }

    public function testTheMeasureIsTheWeightedMeanOfTheChildren(): void
    {
        $session = M::session(M::item('m', M::flow(), M::item('s1').M::item('s2', M::sequencing('<imsss:rollupRules objectiveMeasureWeight="0.5"/>')).M::item('s3', M::sequencing('<imsss:rollupRules objectiveMeasureWeight="0"/>'))));
        $session = $this->walk($session, ['s1' => new AttemptResult(scaledScore: 0.9), 's2' => new AttemptResult(scaledScore: 0.3), 's3' => new AttemptResult(scaledScore: -1.0)]);

        self::assertEqualsWithDelta(0.7, $session->state->activity('m')->objective('#primary')->measure, 1e-9);
        self::assertEqualsWithDelta(0.7, $session->state->activity('org')->objective('#primary')->measure, 1e-9);
    }

    public function testAChildWithoutMeasureWeighsOnTheMean(): void
    {
        $session = $this->walk($this->children('', '', 2), ['s1' => new AttemptResult(scaledScore: 0.8)]);

        self::assertEqualsWithDelta(0.4, $session->state->activity('m')->objective('#primary')->measure, 1e-9);

        $none = $this->walk($this->children('', '', 2), ['s1' => new AttemptResult(true)]);
        self::assertNull($none->state->activity('m')->objective('#primary')->measure);

        $weightless = $this->walk(M::session(M::item('m', M::flow(), M::item('s1', M::sequencing('<imsss:rollupRules objectiveMeasureWeight="0"/>')))), ['s1' => new AttemptResult(scaledScore: 0.8)]);
        self::assertNull($weightless->state->activity('m')->objective('#primary')->measure);
    }

    public function testAnObjectiveSatisfiedByMeasureFollowsItsThreshold(): void
    {
        $byMeasure = '<imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.6</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives>';
        $session = M::session(M::item('m', M::flow($byMeasure), M::item('s1', M::sequencing($byMeasure)).M::item('s2')));
        $session->go(NavigationRequest::Choice, 's1');
        $session->report(new AttemptResult(true, false, 0.6));

        self::assertTrue($session->state->activity('s1')->objective('#primary')->satisfied);
        self::assertSame(0.3, $session->state->activity('m')->objective('#primary')->measure);
        self::assertFalse($session->state->activity('m')->objective('#primary')->satisfied);
        $session->report(new AttemptResult(scaledScore: 0.59));
        self::assertFalse($session->state->activity('s1')->objective('#primary')->satisfied);
        $session->go(NavigationRequest::Choice, 's2');
        $session->report(new AttemptResult(scaledScore: 1.0));
        self::assertEqualsWithDelta(0.795, $session->state->activity('m')->objective('#primary')->measure, 1e-9);
        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
    }

    public function testTheMeasureOfAnActiveActivityMayWait(): void
    {
        $byMeasure = '<imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.5</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives>';
        $session = M::session(M::item('s1', M::sequencing($byMeasure.'<adlseq:rollupConsiderations measureSatisfactionIfActive="false"/>')).M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(scaledScore: 0.9));

        self::assertNull($session->state->activity('s1')->objective('#primary')->satisfied);
        $session->go(NavigationRequest::Continue);
        self::assertTrue($session->state->activity('s1')->objective('#primary')->satisfied);
    }

    public function testTheContentMaySetTheResultsAlone(): void
    {
        $session = M::session(M::item('s1', M::sequencing('<imsss:deliveryControls objectiveSetByContent="true" completionSetByContent="true"/>')).M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);

        self::assertNull($session->state->activity('s1')->completed);
        self::assertNull($session->state->activity('s1')->objective('#primary')->satisfied);

        $default = M::session(M::item('s1').M::item('s2'));
        $default->go(NavigationRequest::Start);
        $default->report(new AttemptResult(false, false));
        $default->go(NavigationRequest::Continue);
        self::assertFalse($default->state->activity('s1')->completed);
        self::assertFalse($default->state->activity('s1')->objective('#primary')->satisfied);
    }

    public function testGlobalObjectivesAreSharedThroughTheirMaps(): void
    {
        $writer = M::sequencing('<imsss:objectives><imsss:primaryObjective objectiveID="p1"><imsss:mapInfo targetObjectiveID="urn:g:safety" writeSatisfiedStatus="true" writeNormalizedMeasure="true"/></imsss:primaryObjective>'
            .'<imsss:objective objectiveID="tools" satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.5</imsss:minNormalizedMeasure><imsss:mapInfo targetObjectiveID="urn:g:tools" readSatisfiedStatus="false" writeSatisfiedStatus="true"/></imsss:objective></imsss:objectives>');
        $reader = M::sequencing(M::rules('pre', [['skip', M::condition('satisfied')]]).'<imsss:objectives><imsss:primaryObjective objectiveID="p3"><imsss:mapInfo targetObjectiveID="urn:g:safety"/></imsss:primaryObjective></imsss:objectives>');
        $session = M::session(M::item('s1', $writer).M::item('s2').M::item('s3', $reader).M::item('s4'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, true, 0.8, objectives: ['tools' => new ObjectiveStatus(measure: 0.7), 'nowhere' => new ObjectiveStatus(true)]));

        self::assertTrue($session->state->global('urn:g:safety')->satisfied);
        self::assertSame(0.8, $session->state->global('urn:g:safety')->measure);
        self::assertTrue($session->state->global('urn:g:tools')->satisfied);
        self::assertNull($session->state->global('urn:g:tools')->measure);
        self::assertSame(['urn:g:safety', 'urn:g:tools'], array_keys($session->state->globals()));
        self::assertSame(0.7, $session->state->activity('s1')->objective('tools')->measure);
        self::assertNull($session->state->activity('s1')->objective('nowhere')->satisfied);

        self::assertSame('s2', $session->go(NavigationRequest::Continue));
        self::assertSame('s4', $session->go(NavigationRequest::Continue));
        self::assertSame(0, $session->state->activity('s3')->attempts);
    }

    public function testAnUnknownValueIsNotWrittenToTheGlobalObjectives(): void
    {
        $writer = '<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="g" writeSatisfiedStatus="true" writeNormalizedMeasure="true"/></imsss:primaryObjective></imsss:objectives>';
        $session = M::session(M::item('m', M::flow($writer), M::item('s1').M::item('s2')));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(false));

        self::assertSame([], $session->state->globals());
        $session->report(new AttemptResult(scaledScore: 0.5));
        self::assertSame(['g' => ['satisfied' => null, 'measure' => 0.25]], $session->state->toArray()['globals']);
    }

    public function testAMapReadsWhatTheGlobalKnowsBeforeWhatTheActivityHolds(): void
    {
        $writer = M::sequencing('<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="g" readSatisfiedStatus="false" readNormalizedMeasure="false" writeSatisfiedStatus="true" writeNormalizedMeasure="true"/></imsss:primaryObjective></imsss:objectives>');
        $reader = M::sequencing('<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="none"/><imsss:mapInfo targetObjectiveID="g"/></imsss:primaryObjective></imsss:objectives><imsss:deliveryControls objectiveSetByContent="true"/>'.M::rules('post', [['continue', M::condition('objectiveMeasureGreaterThan', false, 'measureThreshold="0.1"')]]));
        $session = M::session(M::item('s1', $writer).M::item('s2', $reader).M::item('s3'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(passed: false, scaledScore: 0.2));
        $session->go(NavigationRequest::Continue);
        $session->report(new AttemptResult(passed: true, scaledScore: 0.0));

        self::assertTrue($session->state->activity('s2')->objective('#primary')->satisfied);
        self::assertFalse($session->state->global('g')->satisfied);
        self::assertSame('s3', $session->go(NavigationRequest::Exit));
        self::assertFalse($session->state->activity('s1')->objective('#primary')->satisfied);
    }

    public function testTheChildrenAreDrawnAndReorderedReproducibly(): void
    {
        $items = M::item('s1').M::item('s2').M::item('s3').M::item('s4');
        $draw = static fn (string $controls, int $seed): array => (static function () use ($items, $controls, $seed): array {
            $session = M::session(M::item('m', M::flow('<imsss:randomizationControls '.$controls.'/>'), $items));
            $session->state = SequencingState::begin($seed);
            $session->go(NavigationRequest::Start);

            return $session->state->toArray()['activities']['m']['order'] ?? [];
        })();

        $selected = $draw('selectionTiming="once" selectCount="2"', 7);
        self::assertCount(2, $selected);
        self::assertSame($selected, $draw('selectionTiming="once" selectCount="2"', 7));
        self::assertSame(array_values(array_intersect(['s1', 's2', 's3', 's4'], $selected)), $selected);
        self::assertSame([], $draw('selectionTiming="onEachNewAttempt" selectCount="0"', 7));
        self::assertSame([], $draw('selectionTiming="never" selectCount="2"', 7));
        self::assertSame(['s1', 's2', 's3', 's4'], $draw('selectionTiming="once" selectCount="9"', 7));

        $shuffled = $draw('randomizationTiming="once" reorderChildren="true"', 3);
        self::assertSame(['s1', 's2', 's3', 's4'], $this->sorted($shuffled));
        self::assertSame($shuffled, $draw('randomizationTiming="once" reorderChildren="true"', 3));
        self::assertSame([], $draw('randomizationTiming="once" reorderChildren="false"', 3));
        self::assertSame($shuffled, $draw('selectionTiming="once" selectCount="4" randomizationTiming="once" reorderChildren="true"', 3));
        self::assertSame(['s1', 's2', 's3', 's4'], $draw('selectionTiming="once" selectCount="4" randomizationTiming="once" reorderChildren="false"', 3));
        self::assertSame(['s1', 's2', 's3', 's4'], $draw('selectionTiming="once" selectCount="5" reorderChildren="true"', 3));

        $orders = [];

        for ($seed = 0; $seed < 20; ++$seed) {
            $orders[implode(',', $draw('randomizationTiming="once" reorderChildren="true"', $seed))] = true;
        }

        self::assertGreaterThan(1, \count($orders));
    }

    public function testTheChildrenNotDrawnAreNotOffered(): void
    {
        $session = M::session(M::item('m', M::flow('<imsss:randomizationControls selectionTiming="onEachNewAttempt" selectCount="1"/>'), M::item('s1').M::item('s2').M::item('s3')));
        $session->state = SequencingState::begin(11);

        $first = $session->go(NavigationRequest::Start);
        $others = array_values(array_diff(['s1', 's2', 's3'], [$first]));

        self::assertSame('The activity "'.$others[0].'" is not offered in this attempt.', $session->navigate(NavigationRequest::Choice, $others[0])->reason);
        self::assertSame('sessionEnded', $session->go(NavigationRequest::Continue));
        self::assertNull($session->state->toArray()['activities']['m']['order'] ?? null);

        $drawn = [];

        for ($attempt = 0; $attempt < 12; ++$attempt) {
            $drawn[$session->go(NavigationRequest::Start)] = true;
            $session->go(NavigationRequest::ExitAll);
        }

        self::assertGreaterThan(1, \count($drawn));
    }

    public function testADrawOnceIsKeptForTheSession(): void
    {
        $session = M::session(M::item('m', M::flow('<imsss:randomizationControls selectionTiming="once" selectCount="2"/>'), M::item('s1').M::item('s2').M::item('s3')));
        $session->go(NavigationRequest::Start);

        $order = $session->state->toArray()['activities']['m']['order'] ?? null;
        $session->go(NavigationRequest::ExitAll);
        $session->go(NavigationRequest::Start);

        self::assertSame($order, $session->state->toArray()['activities']['m']['order'] ?? null);
    }

    public function testAnObjectiveSatisfiedByMeasureIsUnknownWithoutAMeasure(): void
    {
        $byMeasure = M::sequencing('<imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"/></imsss:objectives>');
        $session = M::session(M::item('s1', $byMeasure).M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, true));

        self::assertNull($session->state->activity('s1')->objective('#primary')->satisfied);
        $session->report(new AttemptResult(scaledScore: 1.0));
        self::assertTrue($session->state->activity('s1')->objective('#primary')->satisfied);
    }

    public function testARuleHoldsOnlyOnChildrenThatContribute(): void
    {
        $out = M::sequencing('<imsss:rollupRules rollupObjectiveSatisfied="false"/>');
        $rules = '<imsss:rollupRules>'.M::rollupRule('notSatisfied', M::rollupCondition('satisfied'), 'none').'</imsss:rollupRules>';
        $session = self::walk(M::session(M::item('m', M::flow($rules), M::item('s1', $out).M::item('s2', $out))), ['s1' => new AttemptResult(true, false), 's2' => new AttemptResult(true, false)]);

        self::assertNull($session->state->activity('m')->objective('#primary')->satisfied);
        self::assertTrue($session->state->activity('m')->completed);
    }

    /**
     * Rolls up random trees of random results, and checks what the default rules and the measure promise: a cluster is
     * satisfied when all its children are, not satisfied when one is not and all are known, unknown otherwise; its
     * measure is the mean of the measures known over all its children; the answer does not depend on their order.
     */
    public function testRollupPropertiesHoldOnRandomTrees(): void
    {
        $randomizer = new Randomizer(new Mt19937(20_261_120));

        for ($round = 0; $round < 60; ++$round) {
            $results = [];
            $tree = self::randomTree($randomizer, 'n'.$round, 0, $results);
            $mirror = self::mirrored($tree);

            $tree = new ActivityTree($tree);
            $mirror = new ActivityTree($mirror);
            $expected = array_map(static fn (array $pair): array => [$pair[0], null === $pair[1] ? null : round($pair[1], 6)], self::expected($tree->root, $results));
            ksort($expected);
            $observed = $this->observed($tree, $this->play($tree, $results));

            self::assertSame($expected, $observed);
            self::assertSame($observed, $this->observed($mirror, $this->play($mirror, $results)));
        }
    }

    /**
     * @param array<string, array{?bool, ?float}> $results
     */
    private static function randomTree(Randomizer $randomizer, string $identifier, int $depth, array &$results): Activity
    {
        $count = $depth >= 3 ? 0 : $randomizer->getInt($depth > 0 ? 0 : 1, 4);
        $children = [];

        for ($index = 0; $index < $count; ++$index) {
            $children[] = self::randomTree($randomizer, $identifier.'.'.$index, $depth + 1, $results);
        }

        if ([] === $children) {
            $results[$identifier] = [
                [true, false, null][$randomizer->getInt(0, 2)],
                0 === $randomizer->getInt(0, 2) ? null : (float) $randomizer->getInt(-100, 100) / 100,
            ];
        }

        return new Activity($identifier, $identifier, new Sequencing(new ControlMode(), rollupControls: new RollupControls(), deliveryControls: new DeliveryControls(completionSetByContent: true, objectiveSetByContent: true)), $children);
    }

    private static function mirrored(Activity $activity): Activity
    {
        return new Activity($activity->identifier, $activity->title, $activity->sequencing, array_reverse(array_map(self::mirrored(...), $activity->children)));
    }

    /**
     * @param array<string, array{?bool, ?float}> $results
     */
    private function play(ActivityTree $tree, array $results): SequencingState
    {
        $sequencer = new Sequencer();
        $state = SequencingState::begin(1);
        ksort($results);

        foreach ($results as $leaf => [$satisfied, $measure]) {
            $state = $sequencer->navigate($tree, $state, NavigationRequest::Choice, $leaf)->state;
            $state = $sequencer->report($tree, $state, new AttemptResult(passed: $satisfied, scaledScore: $measure));
        }

        return $sequencer->navigate($tree, $state, NavigationRequest::ExitAll)->state;
    }

    /**
     * @param array<string, array{?bool, ?float}> $results
     *
     * @return array<string, array{?bool, ?float}>
     */
    private static function expected(Activity $activity, array $results): array
    {
        if ($activity->isLeaf()) {
            return [$activity->identifier => $results[$activity->identifier]];
        }

        $expected = [];
        $statuses = [];
        $measures = [];

        foreach ($activity->children as $child) {
            $expected += self::expected($child, $results);
            $statuses[] = $expected[$child->identifier][0];
            $measures[] = $expected[$child->identifier][1];
        }

        $known = array_filter($measures, static fn (?float $measure): bool => null !== $measure);
        $expected[$activity->identifier] = [
            match (true) {
                !\in_array(null, $statuses, true) && !\in_array(false, $statuses, true) => true,
                !\in_array(null, $statuses, true) => false,
                default => null,
            },
            [] === $known ? null : array_sum($known) / \count($measures),
        ];

        return $expected;
    }

    /**
     * @return array<string, array{?bool, ?float}>
     */
    private function observed(ActivityTree $tree, SequencingState $state): array
    {
        $observed = [];

        foreach ($tree->activities() as $activity) {
            $objective = $state->activity($activity->identifier)->objective('#primary');
            $observed[$activity->identifier] = [$objective->satisfied, null === $objective->measure ? null : round($objective->measure, 6)];
        }

        ksort($observed);

        return $observed;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
