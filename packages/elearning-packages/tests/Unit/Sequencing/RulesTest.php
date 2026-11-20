<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\ObjectiveStatus;
use Phil\ElearningPackages\Sequencing\Outcome;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingAttempts;
use Phil\ElearningPackages\Sequencing\SequencingData;
use Phil\ElearningPackages\Sequencing\SequencingFlow;
use Phil\ElearningPackages\Sequencing\SequencingRollup;
use Phil\ElearningPackages\Sequencing\SequencingRules;
use Phil\ElearningPackages\Sequencing\SequencingRun;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sequencer::class)]
#[CoversClass(SequencingRun::class)]
#[CoversClass(SequencingData::class)]
#[CoversClass(SequencingFlow::class)]
#[CoversClass(SequencingRules::class)]
#[CoversClass(SequencingRollup::class)]
#[CoversClass(SequencingAttempts::class)]
final class RulesTest extends TestCase
{
    public function testSkipRulesArePassedOverByFlowInBothDirections(): void
    {
        $skip = M::sequencing(M::rules('pre', [['skip', M::condition('always')]]));
        $session = M::session(M::item('s1').M::item('s2', $skip).M::item('s3'));

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertSame('s3', $session->go(NavigationRequest::Continue));
        self::assertSame('s1', $session->go(NavigationRequest::Previous));
        self::assertSame(0, $session->state->activity('s2')->attempts);
        self::assertSame('s2', $session->go(NavigationRequest::Choice, 's2'));
    }

    public function testFlowPassesOverARunOfSkippedActivities(): void
    {
        $skip = M::sequencing(M::rules('pre', [['skip', M::condition('always')]]));
        $session = M::session(M::item('s1', $skip).M::item('s2', $skip).M::item('s3', $skip).M::item('s4', $skip).M::item('s5', $skip).M::item('s6'));

        self::assertSame('s6', $session->go(NavigationRequest::Start));
        self::assertSame('Flow finds no activity to deliver.', $session->navigate(NavigationRequest::Previous)->reason);
    }

    public function testASkippedFirstActivityStartsOnTheNextOne(): void
    {
        $skipSatisfied = M::sequencing(M::rules('pre', [['disabled', M::condition('never-known')], ['skip', M::condition('satisfied')]]));
        $session = M::session(M::item('s1', $skipSatisfied).M::item('s2'));

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        $session->report(new AttemptResult(true, true));
        $session->go(NavigationRequest::ExitAll);
        self::assertSame('s2', $session->go(NavigationRequest::Start));

        $all = M::session(M::item('s1', M::sequencing(M::rules('pre', [['skip', M::condition('always')]]))));
        self::assertSame('sessionEnded', $all->go(NavigationRequest::Start));
        self::assertSame('refused', M::session(M::item('m', M::flow(), M::item('s1', M::sequencing(M::rules('pre', [['skip', M::condition('always')]])))), M::sequencing('<imsss:controlMode flow="true" forwardOnly="true"/>'))->go(NavigationRequest::Previous));
    }

    public function testADisabledActivityIsNeitherChosenNorReachedByFlow(): void
    {
        $disabled = M::sequencing(M::rules('pre', [['disabled', M::condition('attempted', true)]]));
        $session = M::session(M::item('s1').M::item('s2', $disabled));

        self::assertSame('The activity "s2" is disabled.', $session->navigate(NavigationRequest::Choice, 's2')->reason);
        $session->go(NavigationRequest::Start);
        self::assertSame('Flow finds no activity to deliver.', $session->navigate(NavigationRequest::Continue)->reason);
    }

    public function testAnActivityHiddenFromChoiceIsReachedByFlowOnly(): void
    {
        $hidden = M::sequencing(M::rules('pre', [['hiddenFromChoice', M::condition('always')]]));
        $session = M::session(M::item('m', M::flow(), M::item('s1')), M::sequencing('<imsss:controlMode flow="true"/>'.M::rules('pre', [['hiddenFromChoice', M::condition('always')]])));

        self::assertSame('The activity "org" is hidden from choice.', $session->navigate(NavigationRequest::Choice, 's1')->reason);
        self::assertSame('s1', $session->go(NavigationRequest::Start));

        $leaf = M::session(M::item('s1').M::item('s2', $hidden));
        self::assertSame('The activity "s2" is hidden from choice.', $leaf->navigate(NavigationRequest::Choice, 's2')->reason);
    }

    public function testStopForwardTraversalBlocksAChoiceBeyondIt(): void
    {
        $stop = M::sequencing(M::rules('pre', [['stopForwardTraversal', M::condition('completed', true)]]));
        $session = M::session(M::item('s1').M::item('s2', $stop).M::item('s3'));
        $session->go(NavigationRequest::Start);

        self::assertSame('The activity "s2" stops forward traversal.', $session->navigate(NavigationRequest::Choice, 's3')->reason);
        self::assertSame('s2', $session->go(NavigationRequest::Choice, 's2'));
        self::assertSame('The activity "s2" stops forward traversal.', $session->navigate(NavigationRequest::Choice, 's3')->reason);
        self::assertSame('s1', $session->go(NavigationRequest::Choice, 's1'));
    }

    public function testTheAttemptLimitRefusesAnotherAttempt(): void
    {
        $session = M::session(M::item('s1', M::sequencing('<imsss:limitConditions attemptLimit="1"/>')).M::item('s2'));
        $session->go(NavigationRequest::Start);

        self::assertSame('s2', $session->go(NavigationRequest::Continue));
        self::assertSame('The activity "s1" has no attempt left.', $session->navigate(NavigationRequest::Choice, 's1')->reason);
        self::assertSame('s2', $session->navigate(NavigationRequest::Choice, 's1')->state->current);
    }

    public function testAPostConditionRetriesTheActivityUntilItIsSatisfied(): void
    {
        $retry = M::sequencing(M::rules('post', [['retry', M::condition('satisfied', true)]]).'<imsss:limitConditions attemptLimit="2"/>');
        $session = M::session(M::item('s1', $retry).M::item('s2'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, false));

        self::assertSame('s1', $session->go(NavigationRequest::Continue));
        self::assertSame(2, $session->state->activity('s1')->attempts);
        $session->report(new AttemptResult(true, false));
        $refused = $session->navigate(NavigationRequest::Continue);
        self::assertSame(Outcome::Refused, $refused->outcome);
        self::assertSame('The activity "s1" has no attempt left.', $refused->reason);
    }

    public function testARetryOfAClusterStartsAnotherAttemptOnIt(): void
    {
        $retry = M::flow(M::rules('post', [['retry', M::condition('satisfied', true)]]));
        $exitParent = M::sequencing(M::rules('post', [['exitParent', M::condition('always')]]));
        $session = M::session(M::item('m', $retry, M::item('s1').M::item('s2', $exitParent)).M::item('s3'));
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);
        $session->report(new AttemptResult(true, false));

        self::assertSame('s1', $session->go(NavigationRequest::Exit));
        self::assertSame(2, $session->state->activity('m')->attempts);
        self::assertSame(2, $session->state->activity('s1')->attempts);
    }

    public function testExitParentClimbsToTheRootAndEndsTheSession(): void
    {
        $exitParent = M::sequencing(M::rules('post', [['exitParent', M::condition('completed')]]));
        $session = M::session(M::item('m', M::flow(M::rules('post', [['exitParent', M::condition('always')]])), M::item('s1', $exitParent)).M::item('s2'));
        $session->go(NavigationRequest::Start);

        self::assertSame('sessionEnded', $session->go(NavigationRequest::Continue));
        self::assertFalse($session->state->activity('org')->active);
        self::assertSame(0, $session->state->activity('s2')->attempts);

        $rootExit = M::session(M::item('s1', $exitParent), M::flow(M::rules('post', [['exitParent', M::condition('always')]])));
        $rootExit->go(NavigationRequest::Start);
        self::assertSame('sessionEnded', $rootExit->go(NavigationRequest::Exit));
    }

    public function testExitAllAndRetryAllComeFromPostConditions(): void
    {
        $exitAll = M::session(M::item('s1', M::sequencing(M::rules('post', [['exitAll', M::condition('always')]]))).M::item('s2'));
        $exitAll->go(NavigationRequest::Start);
        self::assertSame('sessionEnded', $exitAll->go(NavigationRequest::Continue));

        $rootExitAll = M::session(M::item('m', M::flow(M::rules('post', [['exitParent', M::condition('always')]])), M::item('s1', M::sequencing(M::rules('post', [['exitParent', M::condition('always')]])))), M::flow(M::rules('post', [['exitAll', M::condition('always')]])));
        $rootExitAll->go(NavigationRequest::Start);
        self::assertSame('sessionEnded', $rootExitAll->go(NavigationRequest::Exit));

        $retryAll = M::session(M::item('s1').M::item('s2', M::sequencing(M::rules('post', [['retryAll', M::condition('satisfied', true)]]))));
        $retryAll->go(NavigationRequest::Start);
        $retryAll->go(NavigationRequest::Continue);
        $retryAll->report(new AttemptResult(true, false));
        self::assertSame('s1', $retryAll->go(NavigationRequest::Continue));
        self::assertSame(2, $retryAll->state->activity('org')->attempts);

        $rootRetryAll = M::session(M::item('s1', M::sequencing(M::rules('post', [['exitParent', M::condition('always')]]))), M::flow(M::rules('post', [['retryAll', M::condition('attemptLimitExceeded', true)]]).'<imsss:limitConditions attemptLimit="2"/>'));
        $rootRetryAll->go(NavigationRequest::Start);
        self::assertSame('s1', $rootRetryAll->go(NavigationRequest::Exit));
        self::assertSame(2, $rootRetryAll->state->activity('org')->attempts);
    }

    public function testContinueAndPreviousComeFromPostConditions(): void
    {
        $session = M::session(M::item('s1').M::item('s2', M::sequencing(M::rules('post', [['previous', M::condition('satisfied', true)], ['continue', M::condition('satisfied')]]))).M::item('s3'));
        $session->go(NavigationRequest::Choice, 's2');
        $session->report(new AttemptResult(true, false));

        self::assertSame('s1', $session->go(NavigationRequest::Exit));
        $session->go(NavigationRequest::Choice, 's2');
        $session->report(new AttemptResult(true, true));
        self::assertSame('s3', $session->go(NavigationRequest::Exit));
    }

    public function testAnExitRuleOfAnAncestorEndsItsAttempt(): void
    {
        $exit = M::flow(M::rules('exit', [['exit', M::condition('attempted')]]).M::rules('post', [['continue', M::condition('always')]]));
        $session = M::session(M::item('m', $exit, M::item('s1').M::item('s2')).M::item('s3'));
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, true));

        self::assertSame('s3', $session->go(NavigationRequest::Exit));
        self::assertSame(0, $session->state->activity('s2')->attempts);
        self::assertFalse($session->state->activity('m')->active);
    }

    public function testAnExitRuleOnTheRootEndsTheSession(): void
    {
        $session = M::session(M::item('m', M::flow(), M::item('s1').M::item('s2')), M::flow(M::rules('exit', [['exit', M::condition('attempted')]])));
        $session->go(NavigationRequest::Start);

        self::assertSame('sessionEnded', $session->go(NavigationRequest::Continue));
        self::assertFalse($session->state->activity('m')->active);
    }

    /**
     * @return iterable<string, array{string, AttemptResult, bool}>
     */
    public static function conditions(): iterable
    {
        yield 'satisfied' => [M::condition('satisfied'), new AttemptResult(passed: true), true];
        yield 'not satisfied, unknown' => [M::condition('satisfied', true), new AttemptResult(), true];
        yield 'satisfied, unknown' => [M::condition('satisfied'), new AttemptResult(), false];
        yield 'status known' => [M::condition('objectiveStatusKnown'), new AttemptResult(passed: false), true];
        yield 'status unknown' => [M::condition('objectiveStatusKnown'), new AttemptResult(), false];
        yield 'measure known' => [M::condition('objectiveMeasureKnown'), new AttemptResult(scaledScore: 0.0), true];
        yield 'measure unknown' => [M::condition('objectiveMeasureKnown'), new AttemptResult(), false];
        yield 'measure above' => [M::condition('objectiveMeasureGreaterThan', false, 'measureThreshold="0.5"'), new AttemptResult(scaledScore: 0.6), true];
        yield 'measure at threshold, not above' => [M::condition('objectiveMeasureGreaterThan', false, 'measureThreshold="0.5"'), new AttemptResult(scaledScore: 0.5), false];
        yield 'measure unknown, not above' => [M::condition('objectiveMeasureGreaterThan', false, 'measureThreshold="0.5"'), new AttemptResult(), false];
        yield 'measure below' => [M::condition('objectiveMeasureLessThan', false, 'measureThreshold="0.5"'), new AttemptResult(scaledScore: 0.4), true];
        yield 'measure at threshold, not below' => [M::condition('objectiveMeasureLessThan', false, 'measureThreshold="0.5"'), new AttemptResult(scaledScore: 0.5), false];
        yield 'measure unknown, not below' => [M::condition('objectiveMeasureLessThan', false, 'measureThreshold="0.5"'), new AttemptResult(), false];
        yield 'completed' => [M::condition('completed'), new AttemptResult(true), true];
        yield 'incomplete' => [M::condition('completed'), new AttemptResult(false), false];
        yield 'progress known' => [M::condition('activityProgressKnown'), new AttemptResult(false), true];
        yield 'progress unknown' => [M::condition('activityProgressKnown'), new AttemptResult(), false];
        yield 'attempted' => [M::condition('attempted'), new AttemptResult(), true];
        yield 'attempt limit exceeded' => [M::condition('attemptLimitExceeded'), new AttemptResult(), true];
        yield 'time limit exceeded' => [M::condition('timeLimitExceeded'), new AttemptResult(duration: Duration::ofCentiseconds(6_001)), true];
        yield 'time limit reached, not exceeded' => [M::condition('timeLimitExceeded'), new AttemptResult(duration: Duration::ofCentiseconds(6_000)), false];
        yield 'time unknown' => [M::condition('timeLimitExceeded'), new AttemptResult(), false];
        yield 'outside the time range' => [M::condition('outsideAvailableTimeRange'), new AttemptResult(), false];
        yield 'any of them' => [M::condition('completed').M::condition('satisfied'), new AttemptResult(passed: true), true];
        yield 'objective named' => [M::condition('satisfied', false, 'referencedObjective="extra"'), new AttemptResult(objectives: ['extra' => new ObjectiveStatus(true)]), true];
        yield 'objective named, primary satisfied' => [M::condition('satisfied', false, 'referencedObjective="extra"'), new AttemptResult(passed: true), false];
    }

    #[DataProvider('conditions')]
    public function testEachConditionIsEvaluatedOnTheActivity(string $conditions, AttemptResult $result, bool $holds): void
    {
        $sequencing = M::sequencing(
            M::rules('post', [['continue', $conditions, 'any']])
            .'<imsss:limitConditions attemptLimit="1" attemptAbsoluteDurationLimit="PT1M"/>'
            .'<imsss:objectives><imsss:primaryObjective objectiveID="main"/><imsss:objective objectiveID="extra"/></imsss:objectives>'
            .'<imsss:deliveryControls objectiveSetByContent="true" completionSetByContent="true"/>',
        );
        $session = M::session(M::item('s1', $sequencing).M::item('s2'));
        $session->go(NavigationRequest::Choice, 's1');
        $session->report($result);

        self::assertSame($holds ? 's2' : 'waiting', $session->go(NavigationRequest::Exit));
    }

    public function testAllConditionsMustHoldUnlessAnyIsAsked(): void
    {
        $all = M::sequencing(M::rules('post', [['continue', M::condition('completed').M::condition('satisfied')]]));
        $session = M::session(M::item('s1', $all).M::item('s2'));
        $session->go(NavigationRequest::Choice, 's1');
        $session->report(new AttemptResult(true, false));

        self::assertSame('waiting', $session->go(NavigationRequest::Exit));
        $session->go(NavigationRequest::Choice, 's1');
        $session->report(new AttemptResult(true, true));
        self::assertSame('s2', $session->go(NavigationRequest::Exit));
    }
}
