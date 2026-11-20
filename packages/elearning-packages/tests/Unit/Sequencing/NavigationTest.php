<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\Sequencing\Activity;
use Phil\ElearningPackages\Sequencing\ActivityTree;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\ControlMode;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\Outcome;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\Sequencing;
use Phil\ElearningPackages\Sequencing\SequencingAttempts;
use Phil\ElearningPackages\Sequencing\SequencingData;
use Phil\ElearningPackages\Sequencing\SequencingFlow;
use Phil\ElearningPackages\Sequencing\SequencingResult;
use Phil\ElearningPackages\Sequencing\SequencingRollup;
use Phil\ElearningPackages\Sequencing\SequencingRules;
use Phil\ElearningPackages\Sequencing\SequencingRun;
use Phil\ElearningPackages\Sequencing\SequencingState;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sequencer::class)]
#[CoversClass(SequencingRun::class)]
#[CoversClass(SequencingData::class)]
#[CoversClass(SequencingFlow::class)]
#[CoversClass(SequencingRules::class)]
#[CoversClass(SequencingRollup::class)]
#[CoversClass(SequencingAttempts::class)]
#[CoversClass(SequencingResult::class)]
#[CoversClass(ActivityTree::class)]
#[CoversClass(SequencingRefused::class)]
final class NavigationTest extends TestCase
{
    private function course(string $rootControl = 'flow="true"', string $moduleControl = 'flow="true"'): M
    {
        return M::session(
            M::item('m1', M::sequencing('<imsss:controlMode '.$moduleControl.'/>'), M::item('s1').M::item('s2'))
            .M::item('m2', M::sequencing('<imsss:controlMode '.$moduleControl.'/>'), M::item('s3').M::item('s4')),
            M::sequencing('<imsss:controlMode '.$rootControl.'/>'),
        );
    }

    public function testFlowWalksTheTreeForwardAndBackwardThenEndsTheSession(): void
    {
        $session = $this->course();

        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertTrue($session->state->activity('org')->active);
        self::assertTrue($session->state->activity('m1')->active);
        self::assertSame(1, $session->state->activity('s1')->attempts);
        self::assertSame('s2', $session->go(NavigationRequest::Continue));
        self::assertFalse($session->state->activity('s1')->active);
        self::assertTrue($session->state->activity('s1')->completed);
        self::assertSame('s3', $session->go(NavigationRequest::Continue));
        self::assertFalse($session->state->activity('m1')->active);
        self::assertTrue($session->state->activity('m1')->completed);
        self::assertTrue($session->state->activity('m2')->active);
        self::assertSame('s2', $session->go(NavigationRequest::Previous));
        self::assertSame(2, $session->state->activity('m1')->attempts);
        self::assertSame(2, $session->state->activity('s2')->attempts);
        self::assertFalse($session->state->activity('m2')->active);
        self::assertSame('s3', $session->go(NavigationRequest::Continue));
        self::assertSame('s4', $session->go(NavigationRequest::Continue));
        self::assertSame('s3', $session->go(NavigationRequest::Previous));
        self::assertSame('s4', $session->go(NavigationRequest::Continue));

        $end = $session->navigate(NavigationRequest::Continue);

        self::assertSame(Outcome::SessionEnded, $end->outcome);
        self::assertFalse($end->isDelivered());
        self::assertNull($end->activity);
        self::assertNull($end->reason);
        self::assertNull($session->state->current);
        self::assertFalse($session->state->activity('org')->active);
        self::assertTrue($session->state->activity('m2')->completed);
        // The second attempt on m1 holds s2 alone: s1 is unknown to it, so the root cannot roll up.
        self::assertNull($session->state->activity('m1')->completed);
        self::assertNull($session->state->activity('org')->completed);
    }

    public function testAnotherSessionIsANewAttemptOnTheWholeTree(): void
    {
        $session = $this->course();
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(false, false, -0.5));

        self::assertSame('sessionEnded', $session->go(NavigationRequest::ExitAll));
        self::assertFalse($session->state->activity('s1')->completed);
        self::assertSame('s1', $session->go(NavigationRequest::Start));
        self::assertSame(2, $session->state->activity('org')->attempts);
        self::assertSame(2, $session->state->activity('m1')->attempts);
        self::assertSame(2, $session->state->activity('s1')->attempts);
        self::assertNull($session->state->activity('s1')->completed);
        self::assertNull($session->state->activity('s1')->objective('#primary')->measure);
    }

    public function testANewAttemptOnAClusterKeepsWhatTheControlModesKeep(): void
    {
        $session = $this->course('flow="true"', 'flow="true" useCurrentAttemptObjectiveInfo="false" useCurrentAttemptProgressInfo="false"');
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, true, 0.5));
        $session->go(NavigationRequest::Continue);
        $session->go(NavigationRequest::Continue);
        $session->go(NavigationRequest::Previous);

        self::assertSame(2, $session->state->activity('m1')->attempts);
        self::assertTrue($session->state->activity('s1')->completed);
        self::assertSame(0.5, $session->state->activity('s1')->objective('#primary')->measure);
    }

    public function testStartNeedsFlowOnTheRootAndNoSessionUnderWay(): void
    {
        $session = $this->course('flow="false"');
        $refused = $session->navigate(NavigationRequest::Start);

        self::assertSame(Outcome::Refused, $refused->outcome);
        self::assertSame('Flow finds no activity to deliver.', $refused->reason);
        self::assertSame('s3', $session->go(NavigationRequest::Choice, 's3'));
        self::assertSame('A session is under way: it cannot start again.', $session->navigate(NavigationRequest::Start)->reason);
        self::assertSame('Flow finds no activity to deliver.', $this->course('flow="true"', 'flow="false"')->navigate(NavigationRequest::Start)->reason);
    }

    public function testFlowRequestsNeedFlowOnTheParent(): void
    {
        $session = $this->course('flow="true"', 'flow="true" forwardOnly="true"');

        self::assertSame('Flow is not allowed from the current activity.', $session->navigate(NavigationRequest::Continue)->reason);
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);
        self::assertSame('The parent of the current activity moves forward only.', $session->navigate(NavigationRequest::Previous)->reason);

        $choice = $this->course('flow="false"');
        $choice->go(NavigationRequest::Choice, 's1');
        self::assertSame('s2', $choice->go(NavigationRequest::Continue));
        self::assertSame('s1', $choice->go(NavigationRequest::Choice, 'm1'));
        self::assertSame('No activity precedes by flow.', $choice->navigate(NavigationRequest::Previous)->reason);
    }

    public function testPreviousBeforeTheFirstActivityIsRefusedAfterTheAttemptEnds(): void
    {
        $session = $this->course();
        $session->go(NavigationRequest::Start);

        $refused = $session->navigate(NavigationRequest::Previous);

        self::assertSame(Outcome::Refused, $refused->outcome);
        self::assertSame('No activity precedes by flow.', $refused->reason);
        self::assertSame('s1', $refused->state->current);
        self::assertFalse($refused->state->activity('s1')->active);
    }

    public function testPreviousIntoAClusterEntersItsLastChildUnlessItMovesForwardOnly(): void
    {
        $session = M::session(M::item('m1', M::flow(), M::item('s1').M::item('s2')).M::item('s3'));
        $session->go(NavigationRequest::Choice, 's3');
        self::assertSame('s2', $session->go(NavigationRequest::Previous));

        $forward = M::session(M::item('m1', M::sequencing('<imsss:controlMode flow="true" forwardOnly="true"/>'), M::item('s1').M::item('s2')).M::item('s3'));
        $forward->go(NavigationRequest::Choice, 's3');
        self::assertSame('s1', $forward->go(NavigationRequest::Previous));
    }

    public function testAChoiceOfAClusterFlowsIntoIt(): void
    {
        $session = $this->course();

        self::assertSame('s3', $session->go(NavigationRequest::Choice, 'm2'));
        self::assertSame('s1', $session->go(NavigationRequest::Choice, 's1'));
        self::assertFalse($session->state->activity('m2')->active);
        self::assertSame('s1', $session->go(NavigationRequest::Choice, 's1'));
        self::assertSame(2, $session->state->activity('s1')->attempts);
        self::assertSame('s1', $session->go(NavigationRequest::Choice, 'org'));
    }

    public function testAChoiceIsRefusedWhereChoiceIsClosed(): void
    {
        $session = $this->course('flow="true"', 'flow="true" choice="false"');

        self::assertSame('A choice names an activity of the tree.', $session->navigate(NavigationRequest::Choice, 'nowhere')->reason);
        self::assertSame('A choice names an activity of the tree.', $session->navigate(NavigationRequest::Choice)->reason);
        self::assertSame('The children of "m1" are not chosen.', $session->navigate(NavigationRequest::Choice, 's2')->reason);
        self::assertSame('s3', $session->go(NavigationRequest::Choice, 'm2'));
    }

    public function testAnActivityThatMayNotBeLeftByChoiceStaysUntilItEnds(): void
    {
        $session = $this->course('flow="true"', 'flow="true" choiceExit="false"');
        $session->go(NavigationRequest::Start);

        self::assertSame('The activity "m1" may not be left by a choice.', $session->navigate(NavigationRequest::Choice, 's3')->reason);
        self::assertSame('s2', $session->go(NavigationRequest::Choice, 's2'));
    }

    public function testAForwardOnlyClusterRefusesABackwardChoice(): void
    {
        $session = $this->course('flow="true" forwardOnly="true"');
        $session->go(NavigationRequest::Choice, 's3');

        self::assertSame('The children of "org" are taken forward only.', $session->navigate(NavigationRequest::Choice, 's1')->reason);
        self::assertSame('s4', $session->go(NavigationRequest::Choice, 's4'));
    }

    public function testExitWaitsForAChoiceUnlessTheRootIsReached(): void
    {
        $session = $this->course('flow="true"', 'flow="true"');

        self::assertSame('Only an activity under way is exited.', $session->navigate(NavigationRequest::Exit)->reason);
        $session->go(NavigationRequest::Start);
        self::assertSame('waiting', $session->go(NavigationRequest::Exit));
        self::assertSame('s1', $session->state->current);
        self::assertSame('Only an activity under way is exited.', $session->navigate(NavigationRequest::Exit)->reason);
        self::assertSame('s2', $session->go(NavigationRequest::Continue));
        self::assertSame(1, $session->state->activity('s2')->attempts);
    }

    public function testATreeOfOneLeafEndsWithItsAttempt(): void
    {
        $tree = new ActivityTree(new Activity('only', 'Only', new Sequencing(new ControlMode(flow: true))));
        $sequencer = new Sequencer();
        $start = $sequencer->navigate($tree, SequencingState::begin(1), NavigationRequest::Start);

        self::assertSame('only', $start->activity);
        self::assertSame(Outcome::SessionEnded, $sequencer->navigate($tree, $start->state, NavigationRequest::Exit)->outcome);
        self::assertSame(PackageFormat::Scorm2004, $tree->format);
        self::assertSame([], $tree->warnings);
    }

    public function testExitAllAndSuspendAllNeedASession(): void
    {
        $session = $this->course();

        self::assertSame('No session is under way.', $session->navigate(NavigationRequest::ExitAll)->reason);
        self::assertSame('No session is under way.', $session->navigate(NavigationRequest::SuspendAll)->reason);
        self::assertSame('Only a suspended session, with no activity under way, is resumed.', $session->navigate(NavigationRequest::ResumeAll)->reason);
    }

    public function testASuspendedSessionResumesWhereItStopped(): void
    {
        $session = $this->course();
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Continue);
        $session->report(new AttemptResult(false));

        self::assertSame('sessionEnded', $session->go(NavigationRequest::SuspendAll));
        self::assertNull($session->state->current);
        self::assertSame('s2', $session->state->suspendedActivity);
        self::assertTrue($session->state->activity('s2')->suspended);
        self::assertTrue($session->state->activity('m1')->suspended);
        self::assertTrue($session->state->activity('org')->suspended);
        self::assertFalse($session->state->activity('org')->active);
        self::assertSame('A suspended session is resumed by resume all, not started again.', $session->navigate(NavigationRequest::Start)->reason);

        self::assertSame('s2', $session->go(NavigationRequest::ResumeAll));
        self::assertSame(1, $session->state->activity('s2')->attempts);
        self::assertSame(1, $session->state->activity('org')->attempts);
        self::assertFalse($session->state->activity('s2')->suspended);
        self::assertTrue($session->state->activity('s2')->active);
        self::assertFalse($session->state->activity('s2')->completed);
        self::assertNull($session->state->suspendedActivity);
        self::assertSame('A session is under way: it cannot start again.', $session->navigate(NavigationRequest::Start)->reason);
        self::assertSame('Only a suspended session, with no activity under way, is resumed.', $session->navigate(NavigationRequest::ResumeAll)->reason);
    }

    public function testAChoiceAfterASuspensionClearsIt(): void
    {
        $session = $this->course();
        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::SuspendAll);

        self::assertSame('s3', $session->go(NavigationRequest::Choice, 's3'));
        self::assertNull($session->state->suspendedActivity);
        self::assertFalse($session->state->activity('s1')->suspended);
        self::assertFalse($session->state->activity('m1')->suspended);
        self::assertFalse($session->state->activity('org')->suspended);
        self::assertTrue($session->state->activity('org')->active);
        self::assertSame(1, $session->state->activity('org')->attempts);
    }

    public function testTheContentReportsItsResults(): void
    {
        $session = $this->course();
        $session->go(NavigationRequest::Start);
        $session->report(new AttemptResult(true, true, 1.5));

        self::assertTrue($session->state->activity('s1')->completed);
        self::assertTrue($session->state->activity('s1')->objective('#primary')->satisfied);
        self::assertSame(1.0, $session->state->activity('s1')->objective('#primary')->measure);
        self::assertSame(0.5, $session->state->activity('m1')->objective('#primary')->measure);

        $session->report(new AttemptResult(scaledScore: -3.0));
        self::assertSame(-1.0, $session->state->activity('s1')->objective('#primary')->measure);
        self::assertTrue($session->state->activity('s1')->completed);
    }

    public function testResultsNeedAnActivityUnderWay(): void
    {
        $session = $this->course();

        try {
            $session->report(new AttemptResult(true));
            self::fail('A report without a delivered activity is refused.');
        } catch (SequencingRefused $sequencingRefused) {
            self::assertSame('Results are reported for the activity delivered, while its attempt is under way.', $sequencingRefused->getMessage());
        }

        $session->go(NavigationRequest::Start);
        $session->go(NavigationRequest::Exit);
        $this->expectException(SequencingRefused::class);
        $session->report(new AttemptResult(true));
    }

    public function testTheTreeFindsItsActivities(): void
    {
        $tree = $this->course()->tree;

        self::assertSame(['org', 'm1', 's1', 's2', 'm2', 's3', 's4'], array_map(static fn (Activity $activity): string => $activity->identifier, $tree->activities()));
        self::assertSame(['org', 'm2', 's4'], array_map(static fn (Activity $activity): string => $activity->identifier, $tree->path('s4')));
        self::assertSame('m2', $tree->parent('s4')?->identifier);
        self::assertNull($tree->parent('org'));
        self::assertFalse($tree->has('s5'));
        self::assertFalse($tree->isScorm12());
        self::assertSame('S4', $tree->get('s4')->title);
        self::assertSame('r', $tree->get('s4')->resource);
        self::assertNull($tree->get('m2')->resource);

        $this->expectException(SequencingRefused::class);
        $this->expectExceptionMessageIsOrContains('The activity tree holds no activity "s5".');
        $tree->get('s5');
    }

    public function testAClusterWithoutChildrenOnOfferIsPassedOverByFlow(): void
    {
        $session = M::session(M::item('m', M::flow('<imsss:randomizationControls selectionTiming="once" selectCount="0"/>'), M::item('s1')).M::item('s2'));

        self::assertSame('s2', $session->go(NavigationRequest::Start));
        self::assertSame('sessionEnded', M::session(M::item('m', M::flow('<imsss:randomizationControls selectionTiming="once" selectCount="0"/>'), M::item('s1')))->go(NavigationRequest::Start));
    }

    public function testPreviousStopsAtAnAncestorThatMovesForwardOnly(): void
    {
        $session = self::course('flow="true" forwardOnly="true"');
        $session->go(NavigationRequest::Choice, 's3');

        $refused = $session->navigate(NavigationRequest::Previous);

        self::assertSame('No activity precedes by flow.', $refused->reason);
        self::assertSame('s3', $refused->state->current);
    }

    public function testASuspendedClusterIsNotDelivered(): void
    {
        $exitParent = M::sequencing(M::rules('post', [['exitParent', M::condition('always')]]));
        $session = M::session(M::item('m', M::flow(), M::item('s1', $exitParent).M::item('s2')));
        $session->go(NavigationRequest::Start);

        self::assertSame('waiting', $session->go(NavigationRequest::Exit));
        self::assertSame('m', $session->state->current);
        self::assertSame('sessionEnded', $session->go(NavigationRequest::SuspendAll));
        self::assertSame('The activity "m" is not a leaf.', $session->navigate(NavigationRequest::ResumeAll)->reason);
    }
}
