<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\Activity;
use Phil\ElearningPackages\Sequencing\ActivityTree;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\ObjectiveStatus;
use Phil\ElearningPackages\Sequencing\Outcome;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingAttempts;
use Phil\ElearningPackages\Sequencing\SequencingData;
use Phil\ElearningPackages\Sequencing\SequencingFlow;
use Phil\ElearningPackages\Sequencing\SequencingReader;
use Phil\ElearningPackages\Sequencing\SequencingRollup;
use Phil\ElearningPackages\Sequencing\SequencingRules;
use Phil\ElearningPackages\Sequencing\SequencingRun;
use Phil\ElearningPackages\Sequencing\SequencingState;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The corners of the sequencer: what each request does where two readings of the specification would part.
 */
#[CoversClass(Sequencer::class)]
#[CoversClass(SequencingRun::class)]
#[CoversClass(SequencingFlow::class)]
#[CoversClass(SequencingRollup::class)]
#[CoversClass(SequencingRules::class)]
#[CoversClass(SequencingAttempts::class)]
#[CoversClass(SequencingData::class)]
#[CoversClass(SequencingState::class)]
final class SequencingBehaviourTest extends TestCase
{
    public function testTheOrganizationIsNotCheckedForASkipWhenTheSessionStarts(): void
    {
        $root = M::sequencing('<imsss:controlMode flow="true"/>'.M::rules('pre', [['skip', M::condition('always')]]));
        $session = M::session(M::item('s1').M::item('s2'), $root);

        self::assertSame('s1', $session->go(NavigationRequest::Start));
    }

    public function testARetryOfAllStartsAgainWithoutCheckingTheOrganization(): void
    {
        $root = M::sequencing('<imsss:controlMode flow="true"/>'.M::rules('pre', [['skip', M::condition('always')]]));
        $session = M::session(M::item('s1', M::sequencing(M::rules('post', [['retryAll', M::condition('always')]]))).M::item('s2'), $root);

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertSame('s1', $session->go(NavigationRequest::Continue));
    }

    public function testARetryOfAClusterEntersItWithoutCheckingItForASkip(): void
    {
        $cluster = M::sequencing('<imsss:controlMode flow="true"/>'.M::rules('pre', [['skip', M::condition('attempted')]]).M::rules('post', [['retry', M::condition('always')]]));
        $session = M::session(M::item('m', $cluster, M::item('s1', M::sequencing(M::rules('post', [['exitParent', M::condition('always')]]))).M::item('s2')).M::item('s3'));

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertSame('s1', $session->go(NavigationRequest::Continue));
    }

    public function testAChoiceLeavesTheCurrentActivityByItsOwnPostRules(): void
    {
        $session = M::session(M::item('s1', M::sequencing(M::rules('post', [['continue', M::condition('always')]]))).M::item('s2').M::item('s3').M::item('s4'));

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertSame('s2', $session->go(NavigationRequest::Choice, 's3'));
    }

    public function testNothingFlowsFromAClusterThatDoesNotFlow(): void
    {
        $session = M::session(M::item('m', M::sequencing('<imsss:controlMode choice="true" flow="false"/>'), M::item('s1').M::item('s2')));

        self::assertSame('s1', $session->go(NavigationRequest::Choice, 's1'));

        $result = $session->navigate(NavigationRequest::Continue);

        self::assertSame(Outcome::Refused, $result->outcome);
        self::assertSame('Flow is not allowed from the current activity.', $result->reason);
        self::assertNull($result->state->activity('s1')->completed);
        self::assertTrue($result->state->activity('s1')->active);
    }

    public function testNothingFlowsFromAnOrganizationThatIsTheOnlyActivity(): void
    {
        $tree = new ActivityTree(new Activity('root', 'Root'));
        $state = SequencingState::fromArray($this->state('root', ['root' => $this->record(1, true)]), $tree);

        $result = new Sequencer()->navigate($tree, $state, NavigationRequest::Continue);

        self::assertSame(Outcome::Refused, $result->outcome);
        self::assertSame('Flow is not allowed from the current activity.', $result->reason);
    }

    public function testAnAncestorMayBeChosenWhereTheChildrenAreTakenForwardOnly(): void
    {
        $session = M::session(M::item('m', M::sequencing('<imsss:controlMode flow="true" forwardOnly="true"/>'), M::item('s1').M::item('s2').M::item('s3')));

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertSame('s2', $session->go(NavigationRequest::Continue));
        self::assertSame('s1', $session->go(NavigationRequest::Choice, 'm'));
    }

    public function testASuspensionRollsUpWhatTheStateHeld(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', '', M::item('s1'))));
        $state = SequencingState::fromArray($this->state('s1', [
            'org' => $this->record(1, true),
            'm' => $this->record(1, true),
            's1' => $this->record(1, true, null, ['#primary' => ['satisfied' => true, 'measure' => null]]),
        ]), $tree);

        $result = new Sequencer()->navigate($tree, $state, NavigationRequest::SuspendAll);

        self::assertTrue($result->state->activity('m')->objective('#primary')->satisfied);
        self::assertTrue($result->state->activity('org')->objective('#primary')->satisfied);
    }

    public function testTheRollupGoesFromTheLeafUpInOneReport(): void
    {
        $session = M::session(M::item('m', M::flow(), M::item('s1')));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(passed: true));

        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
        self::assertTrue($session->state->activity('org')->objective('#primary')->satisfied);
    }

    public function testAnUntrackedChildDoesNotStopTheRollupOfTheMeasure(): void
    {
        $untracked = M::sequencing('<imsss:deliveryControls tracked="false"/>');
        $session = M::session(M::item('m', M::flow(), M::item('s1', $untracked).M::item('s2')));
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);
        $session->report(new AttemptResult(scaledScore: 0.5));

        self::assertSame(0.5, $session->state->activity('m')->objective('#primary')->measure);
    }

    public function testHalfOfTheChildrenSatisfiedMeetsAtLeastHalf(): void
    {
        $rule = '<imsss:rollupRules>'.M::rollupRule('satisfied', M::rollupCondition('satisfied'), 'atLeastPercent', 'minimumPercent="0.5"').'</imsss:rollupRules>';
        $session = M::session(M::item('m', M::flow($rule), M::item('s1').M::item('s2').M::item('s3').M::item('s4')));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(passed: true));
        $session->go(NavigationRequest::Continue);
        $session->report(new AttemptResult(passed: true));

        self::assertTrue($session->state->activity('m')->objective('#primary')->satisfied);
    }

    public function testAnActivityWithoutALimitOfTimeNeverExceedsIt(): void
    {
        $leaf = M::sequencing(M::rules('post', [['exitAll', M::condition('timeLimitExceeded')]]));
        $session = M::session(M::item('s1', $leaf).M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(duration: Duration::ofCentiseconds(100)));

        self::assertSame('s2', $session->go(NavigationRequest::Continue));
    }

    public function testTheAttemptOfATrackedLeafEndsCompletedAndSatisfied(): void
    {
        $session = M::session(M::item('s1').M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);

        self::assertTrue($session->state->activity('s1')->completed);
        self::assertTrue($session->state->activity('s1')->objective('#primary')->satisfied);
    }

    public function testAnObjectiveOfUnknownIdentifierDoesNotStopTheReport(): void
    {
        $objectives = '<imsss:objectives><imsss:primaryObjective/><imsss:objective objectiveID="x"/></imsss:objectives>';
        $session = M::session(M::item('s1', M::sequencing($objectives)));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(objectives: ['nope' => new ObjectiveStatus(true, 0.1), 'x' => new ObjectiveStatus(true, -0.5)]));

        self::assertTrue($session->state->activity('s1')->objective('x')->satisfied);
        self::assertSame(-0.5, $session->state->activity('s1')->objective('x')->measure);
    }

    public function testAScaledScoreBelowZeroIsKept(): void
    {
        $session = M::session(M::item('s1'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(scaledScore: -0.5));

        self::assertSame(-0.5, $session->state->activity('s1')->objective('#primary')->measure);

        $session->report(new AttemptResult(scaledScore: -3.0));

        self::assertSame(-1.0, $session->state->activity('s1')->objective('#primary')->measure);
    }

    public function testAMeasureAtTheMinimumSatisfiesAnObjectiveThatTheMeasureDecides(): void
    {
        $objectives = '<imsss:objectives><imsss:primaryObjective/><imsss:objective objectiveID="x" satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.5</imsss:minNormalizedMeasure></imsss:objective></imsss:objectives>';
        $session = M::session(M::item('s1', M::sequencing($objectives)));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(objectives: ['x' => new ObjectiveStatus(null, 0.5)]));

        self::assertTrue($session->state->activity('s1')->objective('x')->satisfied);
    }

    public function testWhatIsNotKnownIsNotKeptInTheState(): void
    {
        $objectives = '<imsss:objectives><imsss:primaryObjective satisfiedByMeasure="true"><imsss:minNormalizedMeasure>0.5</imsss:minNormalizedMeasure></imsss:primaryObjective></imsss:objectives>';
        $session = M::session(M::item('s1', M::sequencing($objectives)));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(completed: true));

        self::assertSame([], $session->state->toArray()['activities']['s1']['objectives']);
    }

    public function testAMapThatDoesNotReadTheSatisfactionLeavesTheGlobalObjectiveAlone(): void
    {
        $unread = '<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="urn:g" readSatisfiedStatus="false"/></imsss:primaryObjective></imsss:objectives>';
        $read = '<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="urn:g"/></imsss:primaryObjective></imsss:objectives>';
        $tree = new SequencingReader()->read(M::scorm2004(M::item('unread', M::sequencing($unread)).M::item('read', M::sequencing($read))));
        $local = ['#primary' => ['satisfied' => false, 'measure' => null]];
        $state = SequencingState::fromArray([
            ...$this->state(null, ['unread' => $this->record(1, false, null, $local), 'read' => $this->record(1, false, null, $local)]),
            'globals' => ['urn:g' => ['satisfied' => true, 'measure' => null]],
        ], $tree);
        $empty = SequencingState::fromArray([
            ...$this->state(null, ['read' => $this->record(1, false, null, $local)]),
        ], $tree);
        $sequencer = new Sequencer();

        self::assertSame('failed', $sequencer->lessonStatus($tree, $state, 'unread'));
        self::assertSame('passed', $sequencer->lessonStatus($tree, $state, 'read'));
        self::assertSame('failed', $sequencer->lessonStatus($tree, $empty, 'read'));
    }

    public function testASatisfactionWrittenToAGlobalObjectiveKeepsItsMeasure(): void
    {
        $objectives = '<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="urn:g" writeSatisfiedStatus="true"/></imsss:primaryObjective></imsss:objectives>';
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing($objectives))));
        $state = SequencingState::fromArray([
            ...$this->state('s1', ['org' => $this->record(1, true), 's1' => $this->record(1, true)]),
            'globals' => ['urn:g' => ['satisfied' => null, 'measure' => 0.4]],
        ], $tree);

        $state = new Sequencer()->report($tree, $state, new AttemptResult(passed: true));

        self::assertTrue($state->global('urn:g')->satisfied);
        self::assertSame(0.4, $state->global('urn:g')->measure);
    }

    public function testAnIncompleteActivityNeverAttemptedReadsAsIncomplete(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1')));
        $state = SequencingState::fromArray($this->state(null, ['s1' => $this->record(0, false, false)]), $tree);

        self::assertSame('incomplete', new Sequencer()->lessonStatus($tree, $state, 's1'));
    }

    public function testASelectionNeedsItsTimingAndItsCount(): void
    {
        $children = M::item('s1').M::item('s2').M::item('s3');
        $withoutTiming = M::session(M::item('m', M::flow('<imsss:randomizationControls selectCount="1" randomizationTiming="once" reorderChildren="true"/>'), $children));
        $withoutTiming->go(NavigationRequest::Start);

        $withoutCount = M::session(M::item('m', M::flow('<imsss:randomizationControls selectionTiming="once" randomizationTiming="once" reorderChildren="true"/>'), $children));
        $withoutCount->go(NavigationRequest::Start);

        self::assertCount(3, $withoutTiming->state->toArray()['activities']['m']['order'] ?? []);
        self::assertCount(3, $withoutCount->state->toArray()['activities']['m']['order'] ?? []);
    }

    public function testAnAllEncompassingSelectionDrawsNothingBeforeTheReordering(): void
    {
        $identifiers = ['s1', 's2', 's3', 's4', 's5', 's6'];
        $children = implode('', array_map(static fn (string $identifier): string => M::item($identifier), $identifiers));
        $session = M::session(M::item('m', M::flow('<imsss:randomizationControls selectCount="6" selectionTiming="once" randomizationTiming="once" reorderChildren="true"/>'), $children));
        $session->go(NavigationRequest::Start);

        $expected = new Randomizer(new Xoshiro256StarStar(hash('sha256', '42|m|0', true)))->shuffleArray($identifiers);

        self::assertSame($expected, $session->state->toArray()['activities']['m']['order']);
    }

    public function testAStateKeepsItsActivitiesWhateverTheLookOfTheirIdentifiers(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('1').M::item('2')));
        $state = SequencingState::fromArray($this->state(null, ['1' => $this->record(2)]), $tree);

        self::assertSame(2, $state->activity('1')->attempts);
        self::assertSame(['1'], array_map(strval(...), array_keys($state->toArray()['activities'])));
    }

    public function testAStateNamesNoActivityTheTreeDoesNotHold(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1')));

        foreach ([['nowhere' => $this->record(1)], ['s1' => 'attempted']] as $activities) {
            try {
                SequencingState::fromArray($this->state(null, $activities), $tree);
                self::fail('The state is accepted.');
            } catch (SequencingRefused $sequencingRefused) {
                self::assertSame('A sequencing state names an activity the tree does not hold.', $sequencingRefused->getMessage());
            }
        }
    }

    public function testAnOrderHoldsStringsOnly(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', '', M::item('5').M::item('6'))));
        $this->expectException(SequencingRefused::class);
        $this->expectExceptionMessageIsOrContains('The state of the activity "m" orders children it does not have.');

        SequencingState::fromArray($this->state(null, ['m' => $this->record(1, false, null, [], [5])]), $tree);
    }

    public function testTheFirstAttemptOfAClusterLeavesWhatItsChildrenHold(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', '', M::item('s1').M::item('s2').M::item('s3'))));
        $state = SequencingState::fromArray($this->state(null, ['s3' => $this->record(1, false, true)]), $tree);

        $result = new Sequencer()->navigate($tree, $state, NavigationRequest::Choice, 's2');

        self::assertSame('s2', $result->activity);
        self::assertTrue($result->state->activity('s3')->completed);
    }

    public function testANewAttemptOfAClusterClearsWhatEveryDescendantHolds(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', '', M::item('c', '', M::item('s1')).M::item('s2'))));
        $state = SequencingState::fromArray($this->state(null, [
            'm' => $this->record(1),
            's1' => $this->record(1, false, true),
        ]), $tree);

        $result = new Sequencer()->navigate($tree, $state, NavigationRequest::Choice, 's2');

        self::assertSame('s2', $result->activity);
        self::assertNull($result->state->activity('s1')->completed);
    }

    public function testTheEndOfTheSessionLeavesNoDrawnOrderToAClusterThatDrawsAgain(): void
    {
        $session = M::session(M::item('m', M::flow('<imsss:randomizationControls randomizationTiming="onEachNewAttempt" reorderChildren="true"/>'), M::item('s1').M::item('s2').M::item('s3')));
        $session->go(NavigationRequest::Start);
        self::assertNotNull($session->state->toArray()['activities']['m']['order']);

        self::assertSame(Outcome::SessionEnded->value, $session->go(NavigationRequest::ExitAll));
        self::assertNull($session->state->toArray()['activities']['m']['order']);
    }

    public function testOnlyTheAttemptsUnderWayAreEndedWhenTheDeliveryLeavesABranch(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', '', M::item('s1')).M::item('s3')));
        $suspended = ['attempts' => 1, 'active' => false, 'suspended' => true, 'completed' => null, 'duration' => null, 'lesson' => null, 'objectives' => [], 'order' => null];
        $state = SequencingState::fromArray($this->state('s1', ['org' => $this->record(1, true), 'm' => $suspended, 's1' => $this->record(1)]), $tree);

        $result = new Sequencer()->navigate($tree, $state, NavigationRequest::Choice, 's3');

        self::assertSame('s3', $result->activity);
        self::assertTrue($result->state->activity('m')->suspended);
    }

    /**
     * @param array<int|string, mixed> $activities
     *
     * @return array<string, mixed>
     */
    private function state(?string $current, array $activities): array
    {
        return ['version' => 1, 'seed' => 42, 'current' => $current, 'suspended' => null, 'activities' => $activities, 'globals' => []];
    }

    /**
     * @param array<string, array{satisfied: ?bool, measure: ?float}> $objectives
     * @param ?list<mixed>                                            $order
     *
     * @return array<string, mixed>
     */
    private function record(int $attempts = 0, bool $active = false, ?bool $completed = null, array $objectives = [], ?array $order = null): array
    {
        return ['attempts' => $attempts, 'active' => $active, 'suspended' => false, 'completed' => $completed, 'duration' => null, 'lesson' => null, 'objectives' => $objectives, 'order' => $order];
    }
}
