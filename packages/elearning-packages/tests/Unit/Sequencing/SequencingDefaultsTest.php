<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Exception\RuntimeRefused;
use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Sequencing\Activity;
use Phil\ElearningPackages\Sequencing\ActivityStatus;
use Phil\ElearningPackages\Sequencing\ActivityTree;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\ChildActivitySet;
use Phil\ElearningPackages\Sequencing\ConditionType;
use Phil\ElearningPackages\Sequencing\ControlMode;
use Phil\ElearningPackages\Sequencing\DeliveryControls;
use Phil\ElearningPackages\Sequencing\Objective;
use Phil\ElearningPackages\Sequencing\ObjectiveMap;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;
use Phil\ElearningPackages\Sequencing\RandomizationControls;
use Phil\ElearningPackages\Sequencing\RandomizationTiming;
use Phil\ElearningPackages\Sequencing\Requirement;
use Phil\ElearningPackages\Sequencing\RollupAction;
use Phil\ElearningPackages\Sequencing\RollupControls;
use Phil\ElearningPackages\Sequencing\RollupRule;
use Phil\ElearningPackages\Sequencing\RuleAction;
use Phil\ElearningPackages\Sequencing\RuleCondition;
use Phil\ElearningPackages\Sequencing\RuleTiming;
use Phil\ElearningPackages\Sequencing\Sequencing;
use Phil\ElearningPackages\Sequencing\SequencingBuilder;
use Phil\ElearningPackages\Sequencing\SequencingReader;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The defaults of the values of the sequencing, the values the reader keeps when an attribute says nothing, and the
 * words of the notes and the refusals.
 */
#[CoversClass(Activity::class)]
#[CoversClass(ActivityStatus::class)]
#[CoversClass(ActivityTree::class)]
#[CoversClass(AttemptResult::class)]
#[CoversClass(ControlMode::class)]
#[CoversClass(DeliveryControls::class)]
#[CoversClass(Objective::class)]
#[CoversClass(PackageFormat::class)]
#[CoversClass(Prerequisites::class)]
#[CoversClass(RandomizationControls::class)]
#[CoversClass(RollupControls::class)]
#[CoversClass(RollupRule::class)]
#[CoversClass(RuleAction::class)]
#[CoversClass(ScormVersion::class)]
#[CoversClass(Sequencing::class)]
#[CoversClass(SequencingBuilder::class)]
#[CoversClass(SequencingReader::class)]
final class SequencingDefaultsTest extends TestCase
{
    public function testTheValuesOfASequencingStartOpen(): void
    {
        $activity = new Activity('a', 'A');
        self::assertTrue($activity->visible);

        $status = new ActivityStatus();
        self::assertSame(0, $status->attempts);
        self::assertFalse($status->active);
        self::assertFalse($status->suspended);

        $mode = new ControlMode();
        self::assertTrue($mode->choice);
        self::assertTrue($mode->choiceExit);
        self::assertFalse($mode->flow);
        self::assertFalse($mode->forwardOnly);
        self::assertTrue($mode->useCurrentAttemptObjectiveInfo);
        self::assertTrue($mode->useCurrentAttemptProgressInfo);

        $delivery = new DeliveryControls();
        self::assertTrue($delivery->tracked);
        self::assertFalse($delivery->completionSetByContent);
        self::assertFalse($delivery->objectiveSetByContent);

        $objective = new Objective();
        self::assertNull($objective->id);
        self::assertFalse($objective->primary);
        self::assertFalse($objective->satisfiedByMeasure);
        self::assertSame(1.0, $objective->minNormalizedMeasure);

        $rule = new RollupRule([new RuleCondition(ConditionType::Satisfied)], RollupAction::Satisfied);
        self::assertSame(ChildActivitySet::All, $rule->childActivitySet);
        self::assertSame(0, $rule->minimumCount);
        self::assertSame(0.0, $rule->minimumPercent);
        self::assertFalse($rule->requireAll);

        self::assertTrue(new Sequencing(objectives: [])->primaryObjective()->primary);
    }

    public function testAnActivityTreeNamesTheActivityItDoesNotHoldWithinOneHundredAndTwentyCharacters(): void
    {
        $tree = new ActivityTree(new Activity('root', 'Root'));

        try {
            $tree->get(str_repeat('é', 200));
            self::fail('The activity is found.');
        } catch (SequencingRefused $sequencingRefused) {
            self::assertSame('The activity tree holds no activity "'.str_repeat('é', 120).'".', $sequencingRefused->getMessage());
        }
    }

    public function testEveryFormatNamesItsManifest(): void
    {
        self::assertSame('cmi5.xml', PackageFormat::Cmi5->manifest());
        self::assertSame('tincan.xml', PackageFormat::Xapi->manifest());
        self::assertSame('imsmanifest.xml', PackageFormat::Scorm12->manifest());
        self::assertSame('imsmanifest.xml', PackageFormat::Scorm2004->manifest());
    }

    #[DataProvider('xapiFormats')]
    public function testOnlyAScormFormatHasARunTime(PackageFormat $format): void
    {
        $this->expectException(RuntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('A cmi5 unit or an xAPI package talks xAPI, not the SCORM run-time API.');

        ScormVersion::of($format);
    }

    /**
     * @return iterable<string, array{PackageFormat}>
     */
    public static function xapiFormats(): iterable
    {
        yield 'cmi5' => [PackageFormat::Cmi5];
        yield 'xAPI' => [PackageFormat::Xapi];
    }

    public function testAnIncompleteStatusIsAnIncompleteResult(): void
    {
        self::assertFalse(AttemptResult::fromRuntime(new RuntimeStatus('incomplete'))->completed);
        self::assertTrue(AttemptResult::fromRuntime(new RuntimeStatus('completed'))->completed);
        self::assertNull(AttemptResult::fromRuntime(new RuntimeStatus('not attempted'))->completed);
    }

    public function testEachRollupActionHasItsRequirement(): void
    {
        $controls = new RollupControls(
            requiredForSatisfied: Requirement::IfAttempted,
            requiredForNotSatisfied: Requirement::IfNotSkipped,
            requiredForCompleted: Requirement::IfNotSuspended,
            requiredForIncomplete: Requirement::Always,
        );

        self::assertSame(Requirement::IfAttempted, $controls->requiredFor(RollupAction::Satisfied));
        self::assertSame(Requirement::IfNotSkipped, $controls->requiredFor(RollupAction::NotSatisfied));
        self::assertSame(Requirement::IfNotSuspended, $controls->requiredFor(RollupAction::Completed));
        self::assertSame(Requirement::Always, $controls->requiredFor(RollupAction::Incomplete));
    }

    public function testEachRuleActionHasItsTiming(): void
    {
        self::assertSame(RuleTiming::Pre, RuleAction::Skip->timing());
        self::assertSame(RuleTiming::Pre, RuleAction::Disabled->timing());
        self::assertSame(RuleTiming::Pre, RuleAction::HiddenFromChoice->timing());
        self::assertSame(RuleTiming::Pre, RuleAction::StopForwardTraversal->timing());
        self::assertSame(RuleTiming::Exit, RuleAction::Exit->timing());
        self::assertSame(RuleTiming::Post, RuleAction::ExitParent->timing());
        self::assertSame(RuleTiming::Post, RuleAction::Retry->timing());
    }

    /**
     * @return iterable<string, array{RandomizationTiming, ?int, RandomizationTiming, bool, bool}>
     */
    public static function randomizations(): iterable
    {
        yield 'a selection alone' => [RandomizationTiming::Once, 1, RandomizationTiming::Never, false, true];
        yield 'a reordering alone' => [RandomizationTiming::Never, null, RandomizationTiming::Once, true, true];
        yield 'both' => [RandomizationTiming::Once, 1, RandomizationTiming::Once, true, true];
        yield 'a selection timing without a count' => [RandomizationTiming::Once, null, RandomizationTiming::Never, false, false];
        yield 'a count without a selection timing' => [RandomizationTiming::Never, 2, RandomizationTiming::Never, false, false];
        yield 'a reordering timing without reordering' => [RandomizationTiming::Never, null, RandomizationTiming::Once, false, false];
        yield 'reordering without a timing' => [RandomizationTiming::Never, null, RandomizationTiming::Never, true, false];
        yield 'a count and a reordering timing apart' => [RandomizationTiming::Never, 2, RandomizationTiming::Once, false, false];
        yield 'a selection timing and a reordering apart' => [RandomizationTiming::Once, null, RandomizationTiming::Never, true, false];
    }

    #[DataProvider('randomizations')]
    public function testTheControlsAreActiveWhenTheyDrawOrReorder(RandomizationTiming $selection, ?int $count, RandomizationTiming $randomization, bool $reorder, bool $active): void
    {
        self::assertSame($active, new RandomizationControls($selection, $count, $randomization, $reorder)->isActive());
    }

    public function testWhatTheReaderKeepsWhenAnAttributeSaysNothing(): void
    {
        $sequencing = M::sequencing(
            '<imsss:controlMode flow="true"/>'
            .'<imsss:rollupRules><imsss:rollupRule><imsss:rollupConditions><imsss:rollupCondition condition="satisfied"/></imsss:rollupConditions><imsss:rollupAction action="satisfied"/></imsss:rollupRule></imsss:rollupRules>'
            .'<adlseq:rollupConsiderations/>'
            .'<imsss:objectives><imsss:primaryObjective><imsss:mapInfo targetObjectiveID="urn:g"/></imsss:primaryObjective><imsss:objective objectiveID="x"/></imsss:objectives>'
            .'<imsss:randomizationControls selectCount="1" selectionTiming="once"/>'
            .'<imsss:deliveryControls/>',
        );
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', $sequencing)));
        $read = $tree->get('s1')->sequencing;

        self::assertEquals(new ControlMode(true, true, true, false, true, true), $read->controlMode);
        self::assertSame(0, $read->rollupRules[0]->minimumCount);
        self::assertSame(0.0, $read->rollupRules[0]->minimumPercent);
        self::assertFalse($read->rollupRules[0]->requireAll);
        self::assertTrue($read->rollupControls->objectiveSatisfied);
        self::assertTrue($read->rollupControls->progressCompletion);
        self::assertTrue($read->rollupControls->measureSatisfactionIfActive);
        self::assertEquals(new Objective(null, true, false, 1.0, [new ObjectiveMap('urn:g')]), $read->objectives[0]);
        self::assertEquals(new Objective('x', false, false, 1.0), $read->objectives[1]);
        self::assertFalse($read->randomizationControls->reorderChildren);
        self::assertEquals(new DeliveryControls(true, false, false), $read->deliveryControls);
        self::assertSame([], $tree->warnings);
    }

    public function testTheMeasuresOfTheBordersAreAccepted(): void
    {
        $sequencing = M::sequencing(
            M::rules('pre', [['skip', M::condition('objectiveMeasureGreaterThan', false, 'measureThreshold="1"').M::condition('objectiveMeasureLessThan', false, 'measureThreshold="-1"')]])
            .'<imsss:objectives><imsss:primaryObjective><imsss:minNormalizedMeasure>-0.5</imsss:minNormalizedMeasure></imsss:primaryObjective><imsss:objective objectiveID="low"><imsss:minNormalizedMeasure>-1</imsss:minNormalizedMeasure></imsss:objective></imsss:objectives>',
        );
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', $sequencing)));
        $read = $tree->get('s1')->sequencing;

        self::assertSame([], $tree->warnings);
        self::assertSame(1.0, $read->preConditionRules[0]->conditions[0]->measureThreshold);
        self::assertSame(-1.0, $read->preConditionRules[0]->conditions[1]->measureThreshold);
        self::assertSame(-0.5, $read->objectives[0]->minNormalizedMeasure);
        self::assertSame(-1.0, $read->objectives[1]->minNormalizedMeasure);
    }

    public function testScorm12ItemsOpenFlowAndChoiceAndTrackEverything(): void
    {
        $tree = new SequencingReader()->read(M::scorm12(M::item('s1').M::item('s2', '', '', '<adlcp:prerequisites type="AICC_SCRIPT">s1</adlcp:prerequisites>')));
        $sequencing = $tree->get('s1')->sequencing;

        self::assertEquals(new ControlMode(flow: true), $sequencing->controlMode);
        self::assertEquals(new DeliveryControls(true, true, true), $sequencing->deliveryControls);
        self::assertSame(['s1'], $tree->get('s2')->prerequisites?->identifiers());
        self::assertSame([], $tree->warnings);
    }

    public function testTheVisibilityIsReadWhateverTheCase(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004('<item identifier="a" identifierref="r" isvisible="FALSE"><title>A</title></item><item identifier="b" identifierref="r" isvisible="True"><title>B</title></item>'));

        self::assertFalse($tree->get('a')->visible);
        self::assertTrue($tree->get('b')->visible);
    }

    public function testOnlyAnItemLaunchesAResource(): void
    {
        $organization = '<item identifier="a" identifierref="r"><title>A</title></item><item identifier="b" identifierref=""><title>B</title></item><item identifier="c"><title>C</title></item>';
        $xml = str_replace('<organization identifier="org">', '<organization identifier="org" identifierref="r">', M::scorm2004($organization));
        $tree = new SequencingReader()->read($xml);

        self::assertSame('r', $tree->get('a')->resource);
        self::assertNull($tree->get('b')->resource);
        self::assertNull($tree->get('c')->resource);
        self::assertNull($tree->root->resource);
    }

    public function testTheVisibilityAndTheResourceOfNestedItems(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', '', M::item('s1'))));

        self::assertSame('r', $tree->get('s1')->resource);
        self::assertNull($tree->get('m')->resource);
    }

    public function testPrerequisitesOfAnUnknownTypeAreLeftOutAndTheTypeIsShownShort(): void
    {
        $type = str_repeat('é', 60);
        $tree = new SequencingReader()->read(M::scorm12(M::item('s1').M::item('s2', '', '', '<adlcp:prerequisites type="'.$type.'">s1</adlcp:prerequisites>')));

        self::assertSame(['The prerequisites of the item "s2" are of the type "'.str_repeat('é', 40).'", which the reader does not know: they are left out.'], $tree->warnings);
        self::assertNull($tree->get('s2')->prerequisites);
    }

    public function testASequencingEntryTheCollectionDoesNotHoldIsShownShort(): void
    {
        $reference = str_repeat('é', 200);
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('', 'IDRef="'.$reference.'"'))));

        self::assertSame(['The sequencing of "s1" names the entry "'.str_repeat('é', 120).'", which the sequencing collection does not hold.'], $tree->warnings);
    }

    public function testAnOrganizationTheManifestDoesNotHoldIsShownShort(): void
    {
        try {
            new SequencingReader()->read(M::scorm2004(M::item('s1')), str_repeat('é', 200));
            self::fail('The organization is found.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The SCORM manifest has no organization "'.str_repeat('é', 120).'".', $packageRefused->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, callable(Sequencing): mixed, mixed}>
     */
    public static function skippedBeforeTheValid(): iterable
    {
        $always = M::condition('always');

        yield 'a rule with no action it may take' => [M::rules('pre', [['leave', $always], ['skip', $always]]), static fn (Sequencing $sequencing): int => \count($sequencing->preConditionRules), 1];
        yield 'a rule with no conditions element' => ['<imsss:sequencingRules><imsss:preConditionRule><imsss:ruleAction action="skip"/></imsss:preConditionRule><imsss:preConditionRule><imsss:ruleConditions>'.$always.'</imsss:ruleConditions><imsss:ruleAction action="skip"/></imsss:preConditionRule></imsss:sequencingRules>', static fn (Sequencing $sequencing): int => \count($sequencing->preConditionRules), 1];
        yield 'a rule with a condition it may not test' => [M::rules('pre', [['skip', M::condition('sunny')], ['skip', $always]]), static fn (Sequencing $sequencing): int => \count($sequencing->preConditionRules), 1];
        yield 'a rule with an empty conditions element' => [M::rules('pre', [['skip', ''], ['skip', $always]]), static fn (Sequencing $sequencing): int => \count($sequencing->preConditionRules), 1];
        yield 'a rollup rule with no action it may take' => ['<imsss:rollupRules>'.M::rollupRule('passed', M::rollupCondition('satisfied')).M::rollupRule('satisfied', M::rollupCondition('satisfied')).'</imsss:rollupRules>', static fn (Sequencing $sequencing): int => \count($sequencing->rollupRules), 1];
        yield 'a rollup rule with no conditions element' => ['<imsss:rollupRules><imsss:rollupRule><imsss:rollupAction action="satisfied"/></imsss:rollupRule>'.M::rollupRule('satisfied', M::rollupCondition('satisfied')).'</imsss:rollupRules>', static fn (Sequencing $sequencing): int => \count($sequencing->rollupRules), 1];
        yield 'a rollup rule with a condition it may not test' => ['<imsss:rollupRules>'.M::rollupRule('satisfied', M::rollupCondition('always')).M::rollupRule('satisfied', M::rollupCondition('satisfied')).'</imsss:rollupRules>', static fn (Sequencing $sequencing): int => \count($sequencing->rollupRules), 1];
        yield 'a map with no target' => ['<imsss:objectives><imsss:primaryObjective><imsss:mapInfo/><imsss:mapInfo targetObjectiveID="g"/></imsss:primaryObjective></imsss:objectives>', static fn (Sequencing $sequencing): int => \count($sequencing->primaryObjective()->maps), 1];
        yield 'an objective with no identifier' => ['<imsss:objectives><imsss:objective/><imsss:objective objectiveID="b"/></imsss:objectives>', static fn (Sequencing $sequencing): array => array_map(static fn (Objective $objective): ?string => $objective->id, $sequencing->objectives), [null, 'b']];
    }

    /**
     * @param callable(Sequencing): mixed $read
     */
    #[DataProvider('skippedBeforeTheValid')]
    public function testWhatIsLeftOutDoesNotEndTheReadingOfTheOthers(string $sequencing, callable $read, mixed $expected): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing($sequencing))));

        self::assertSame($expected, $read($tree->get('s1')->sequencing));
    }

    public function testARuleWithNoConditionsIsLeftOut(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing(M::rules('pre', [['skip', '']])))));

        self::assertSame([], $tree->get('s1')->sequencing->preConditionRules);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wholeNumbers(): iterable
    {
        yield 'letters after' => ['3x'];
        yield 'letters before' => ['x3'];
        yield 'letters' => ['many'];
        yield 'a sign' => ['+3'];
    }

    #[DataProvider('wholeNumbers')]
    public function testAnAttemptLimitIsAWholeNumberFromStartToEnd(string $value): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('<imsss:limitConditions attemptLimit="'.$value.'"/>'))));

        self::assertNull($tree->get('s1')->sequencing->limitConditions->attemptLimit);
        self::assertSame(['The attempt limit of the activity "s1" is not a whole number: no limit applies.'], $tree->warnings);
    }

    #[DataProvider('wholeNumbers')]
    public function testAMinimumCountIsAWholeNumberFromStartToEnd(string $value): void
    {
        $rule = M::rollupRule('satisfied', M::rollupCondition('satisfied'), 'atLeastCount', 'minimumCount="'.$value.'"');
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('<imsss:rollupRules>'.$rule.'</imsss:rollupRules>'))));

        self::assertSame(0, $tree->get('s1')->sequencing->rollupRules[0]->minimumCount);
    }

    public function testAMinimumPercentMissingIsZero(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('<imsss:rollupRules>'.M::rollupRule('satisfied', M::rollupCondition('satisfied'), 'atLeastCount', 'minimumCount="4"').'</imsss:rollupRules>'))));

        self::assertSame(4, $tree->get('s1')->sequencing->rollupRules[0]->minimumCount);
        self::assertSame(0.0, $tree->get('s1')->sequencing->rollupRules[0]->minimumPercent);
    }

    #[DataProvider('wholeNumbers')]
    public function testASelectCountIsAWholeNumberFromStartToEnd(string $value): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('<imsss:randomizationControls selectionTiming="once" selectCount="'.$value.'"/>'))));

        self::assertNull($tree->get('s1')->sequencing->randomizationControls->selectCount);
        self::assertSame(['The select count of the activity "s1" is not a whole number: every child is offered.'], $tree->warnings);
    }

    public function testAPrerequisitesExpressionIsReadFromItsFirstToItsLastCharacter(): void
    {
        $this->expectRefusal('a2*{b}', 'The prerequisites "a2*{b}" cannot be read: "*" is unexpected.');
        $this->expectRefusal('2a*{b}', 'The prerequisites "2a*{b}" cannot be read: "*" is unexpected.');
    }

    public function testAnItemMayBeNamedWithLettersOfAnyScript(): void
    {
        self::assertSame(['módulo'], Prerequisites::parse('módulo')->identifiers());
        self::assertSame(['модуль', 'b'], Prerequisites::parse('модуль & b')->identifiers());
    }

    public function testDeepNestingIsRefusedFromTheThirtySecondLevel(): void
    {
        foreach ([
            'negation' => [static fn (int $depth): string => str_repeat('~', $depth).'a'],
            'first of a set' => [static fn (int $depth): string => str_repeat('1*{', $depth).'a'.str_repeat('}', $depth)],
            'next of a set' => [static fn (int $depth): string => str_repeat('1*{b,', $depth).'a'.str_repeat('}', $depth)],
        ] as $kind => [$build]) {
            self::assertInstanceOf(Prerequisites::class, Prerequisites::parse($build(Prerequisites::MAX_DEPTH - 1)), $kind);

            try {
                Prerequisites::parse($build(Prerequisites::MAX_DEPTH));
                self::fail('The expression is accepted: '.$kind);
            } catch (PackageRefused $packageRefused) {
                self::assertStringContainsString('it nests deeper than 32 levels', $packageRefused->getMessage(), $kind);
            }
        }
    }

    public function testAStatusAndAnExpressionAreShownShort(): void
    {
        $status = '"'.str_repeat('é', 200).'"';

        try {
            Prerequisites::parse('a = '.$status);
            self::fail('The expression is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The prerequisites "a = "'.str_repeat('é', 115).'" cannot be read: ""'.str_repeat('é', 39).'" is not a status.', $packageRefused->getMessage());
        }
    }

    private function expectRefusal(string $expression, string $message): void
    {
        try {
            Prerequisites::parse($expression);
            self::fail('The expression is accepted: '.$expression);
        } catch (PackageRefused $packageRefused) {
            self::assertSame($message, $packageRefused->getMessage());
        }
    }
}
