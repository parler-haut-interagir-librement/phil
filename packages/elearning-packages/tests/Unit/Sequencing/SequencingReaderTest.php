<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Sequencing;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\Activity;
use Phil\ElearningPackages\Sequencing\ActivityTree;
use Phil\ElearningPackages\Sequencing\ChildActivitySet;
use Phil\ElearningPackages\Sequencing\ConditionType;
use Phil\ElearningPackages\Sequencing\ControlMode;
use Phil\ElearningPackages\Sequencing\DeliveryControls;
use Phil\ElearningPackages\Sequencing\LimitConditions;
use Phil\ElearningPackages\Sequencing\Objective;
use Phil\ElearningPackages\Sequencing\ObjectiveMap;
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
use Phil\ElearningPackages\Sequencing\SequencingRule;
use Phil\ElearningPackages\Tests\Fixture\SequencingManifests as M;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequencingReader::class)]
#[CoversClass(SequencingBuilder::class)]
#[CoversClass(ActivityTree::class)]
#[CoversClass(Activity::class)]
#[CoversClass(Sequencing::class)]
#[CoversClass(ControlMode::class)]
#[CoversClass(SequencingRule::class)]
#[CoversClass(RuleCondition::class)]
#[CoversClass(RuleAction::class)]
#[CoversClass(RuleTiming::class)]
#[CoversClass(ConditionType::class)]
#[CoversClass(RollupRule::class)]
#[CoversClass(RollupAction::class)]
#[CoversClass(RollupControls::class)]
#[CoversClass(Requirement::class)]
#[CoversClass(ChildActivitySet::class)]
#[CoversClass(Objective::class)]
#[CoversClass(ObjectiveMap::class)]
#[CoversClass(LimitConditions::class)]
#[CoversClass(RandomizationControls::class)]
#[CoversClass(RandomizationTiming::class)]
#[CoversClass(DeliveryControls::class)]
final class SequencingReaderTest extends TestCase
{
    private const string FULL = <<<'XML'
        <imsss:sequencing IDRef="shared">
          <imsss:controlMode choice="false" choiceExit="0" flow="1" forwardOnly="true" useCurrentAttemptObjectiveInfo="false" useCurrentAttemptProgressInfo="false"/>
          <imsss:sequencingRules>
            <imsss:preConditionRule>
              <imsss:ruleConditions conditionCombination="any">
                <imsss:ruleCondition condition="satisfied" operator="not" referencedObjective="extra"/>
                <imsss:ruleCondition condition="objectiveMeasureGreaterThan" measureThreshold="-0.25"/>
              </imsss:ruleConditions>
              <imsss:ruleAction action="skip"/>
            </imsss:preConditionRule>
            <imsss:exitConditionRule>
              <imsss:ruleConditions><imsss:ruleCondition condition="completed" operator="noOp"/></imsss:ruleConditions>
              <imsss:ruleAction action="exit"/>
            </imsss:exitConditionRule>
            <imsss:postConditionRule>
              <imsss:ruleConditions><imsss:ruleCondition condition="always" referencedObjective=""/></imsss:ruleConditions>
              <imsss:ruleAction action="retryAll"/>
            </imsss:postConditionRule>
          </imsss:sequencingRules>
          <imsss:limitConditions attemptLimit="3" attemptAbsoluteDurationLimit="PT1H30M"/>
          <imsss:rollupRules rollupObjectiveSatisfied="false" rollupProgressCompletion="false" objectiveMeasureWeight="0.25">
            <imsss:rollupRule childActivitySet="atLeastPercent" minimumPercent="0.75" minimumCount="2">
              <imsss:rollupConditions conditionCombination="all">
                <imsss:rollupCondition condition="attempted"/>
                <imsss:rollupCondition operator="not" condition="timeLimitExceeded"/>
              </imsss:rollupConditions>
              <imsss:rollupAction action="notSatisfied"/>
            </imsss:rollupRule>
          </imsss:rollupRules>
          <adlseq:rollupConsiderations requiredForSatisfied="ifAttempted" requiredForNotSatisfied="ifNotSkipped" requiredForCompleted="ifNotSuspended" requiredForIncomplete="always" measureSatisfactionIfActive="false"/>
          <imsss:objectives>
            <imsss:primaryObjective objectiveID="main" satisfiedByMeasure="true">
              <imsss:minNormalizedMeasure>0.8</imsss:minNormalizedMeasure>
              <imsss:mapInfo targetObjectiveID="urn:g:main" readSatisfiedStatus="false" readNormalizedMeasure="false" writeSatisfiedStatus="true" writeNormalizedMeasure="true"/>
            </imsss:primaryObjective>
            <imsss:objective objectiveID="extra"><imsss:mapInfo targetObjectiveID="urn:g:extra"/></imsss:objective>
          </imsss:objectives>
          <imsss:randomizationControls randomizationTiming="onEachNewAttempt" selectCount="2" reorderChildren="true" selectionTiming="once"/>
          <imsss:deliveryControls tracked="false" completionSetByContent="true" objectiveSetByContent="true"/>
        </imsss:sequencing>
        XML;

    public function testEverySequencingElementIsRead(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('m', self::FULL, M::item('s1').M::item('s2'))));
        $sequencing = $tree->get('m')->sequencing;

        self::assertEquals(new ControlMode(false, false, true, true, false, false), $sequencing->controlMode);
        self::assertEquals([new SequencingRule([new RuleCondition(ConditionType::Satisfied, true, 'extra'), new RuleCondition(ConditionType::ObjectiveMeasureGreaterThan, false, null, -0.25)], RuleAction::Skip, false)], $sequencing->rules(RuleTiming::Pre));
        self::assertEquals([new SequencingRule([new RuleCondition(ConditionType::Completed)], RuleAction::Exit)], $sequencing->rules(RuleTiming::Exit));
        self::assertEquals([new SequencingRule([new RuleCondition(ConditionType::Always)], RuleAction::RetryAll)], $sequencing->rules(RuleTiming::Post));
        self::assertEquals(new LimitConditions(3, Duration::ofCentiseconds(540_000)), $sequencing->limitConditions);
        self::assertEquals([new RollupRule([new RuleCondition(ConditionType::Attempted), new RuleCondition(ConditionType::TimeLimitExceeded, true)], RollupAction::NotSatisfied, ChildActivitySet::AtLeastPercent, 2, 0.75, true)], $sequencing->rollupRules);
        self::assertEquals(new RollupControls(false, false, 0.25, Requirement::IfAttempted, Requirement::IfNotSkipped, Requirement::IfNotSuspended, Requirement::Always, false), $sequencing->rollupControls);
        self::assertEquals([
            new Objective('main', true, true, 0.8, [new ObjectiveMap('urn:g:main', false, false, true, true)]),
            new Objective('extra', false, false, 1.0, [new ObjectiveMap('urn:g:extra')]),
        ], $sequencing->objectives);
        self::assertSame('main', $sequencing->primaryObjective()->key());
        self::assertSame('extra', $sequencing->objective('extra')->id);
        self::assertSame('main', $sequencing->objective('none')->id);
        self::assertSame('main', $sequencing->objective(null)->id);
        self::assertEquals(new RandomizationControls(RandomizationTiming::Once, 2, RandomizationTiming::OnEachNewAttempt, true), $sequencing->randomizationControls);
        self::assertEquals(new DeliveryControls(false, true, true), $sequencing->deliveryControls);
        self::assertSame(['The sequencing of "m" names the entry "shared", which the sequencing collection does not hold.'], $tree->warnings);
    }

    public function testAnActivityWithoutSequencingHasTheDefaults(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1').M::item('s2', M::sequencing(''))));

        self::assertEquals(new Sequencing(), $tree->get('s1')->sequencing);
        self::assertEquals(new Sequencing(), $tree->get('s2')->sequencing);
        self::assertEquals(new Sequencing(), $tree->root->sequencing);
        self::assertSame(Objective::PRIMARY, $tree->root->sequencing->primaryObjective()->key());
        self::assertSame('Course', $tree->root->title);
        self::assertNull($tree->root->resource);
        self::assertSame(PackageFormat::Scorm2004, $tree->format);
        self::assertSame([], $tree->warnings);
        self::assertTrue($tree->get('s1')->isLeaf());
        self::assertFalse($tree->root->isLeaf());
        self::assertTrue($tree->get('s1')->visible);
        self::assertNull($tree->get('s1')->prerequisites);
    }

    public function testAnEntryOfTheCollectionFillsWhatTheItemDoesNotSay(): void
    {
        $collection = '<imsss:sequencingCollection>'
            .'<imsss:sequencing ID="shared"><imsss:controlMode flow="true" choice="false"/><imsss:limitConditions attemptLimit="2"/></imsss:sequencing>'
            .'<imsss:sequencing ID="shared"><imsss:limitConditions attemptLimit="9"/></imsss:sequencing>'
            .'<imsss:sequencing><imsss:limitConditions attemptLimit="7"/></imsss:sequencing>'
            .'</imsss:sequencingCollection>';
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('<imsss:controlMode flow="false"/>', 'IDRef="shared"')).M::item('s2', M::sequencing('', 'IDRef="shared"')), '', $collection));

        self::assertFalse($tree->get('s1')->sequencing->controlMode->flow);
        self::assertTrue($tree->get('s1')->sequencing->controlMode->choice);
        self::assertSame(2, $tree->get('s1')->sequencing->limitConditions->attemptLimit);
        self::assertTrue($tree->get('s2')->sequencing->controlMode->flow);
        self::assertFalse($tree->get('s2')->sequencing->controlMode->choice);
        self::assertSame([], $tree->warnings);
    }

    public function testAnotherOrganizationMayBeRead(): void
    {
        $xml = str_replace('</organizations>', '<organization identifier="other"><title>Other</title><item identifier="o1" identifierref="r" isvisible="false"><title>O1</title></item></organization></organizations>', M::scorm2004(M::item('s1')));
        $tree = new SequencingReader()->read($xml, 'other');

        self::assertSame('other', $tree->root->identifier);
        self::assertFalse($tree->get('o1')->visible);

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The SCORM manifest has no organization "none".');
        new SequencingReader()->read($xml, 'none');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function valuesSetAside(): iterable
    {
        yield 'a boolean' => ['<imsss:controlMode flow="yes"/>', 'The flow of the activity "s1" is not true or false: false applies.'];
        yield 'a threshold' => [M::rules('pre', [['skip', M::condition('objectiveMeasureLessThan', false, 'measureThreshold="2"')]]), 'A number of the activity "s1" is not a decimal from -1 to 1: 0 applies.'];
        yield 'a weight' => ['<imsss:rollupRules objectiveMeasureWeight="heavy"/>', 'A number of the activity "s1" is not a decimal from 0 to 1: 1 applies.'];
        yield 'an attempt limit' => ['<imsss:limitConditions attemptLimit="-1"/>', 'The attempt limit of the activity "s1" is not a whole number: no limit applies.'];
        yield 'a duration' => ['<imsss:limitConditions attemptAbsoluteDurationLimit="1 hour"/>', 'The duration limit of the activity "s1" is not a duration: no limit applies.'];
        yield 'a consideration' => ['<adlseq:rollupConsiderations requiredForCompleted="sometimes"/>', 'The requiredForCompleted of the activity "s1" is not one SCORM defines: always applies.'];
        yield 'a select count' => ['<imsss:randomizationControls selectCount="two" selectionTiming="once"/>', 'The select count of the activity "s1" is not a whole number: every child is offered.'];
        yield 'a timing' => ['<imsss:randomizationControls randomizationTiming="daily"/>', 'The randomizationTiming of the activity "s1" is not one SCORM defines: never applies.'];
        yield 'a rule action' => [M::rules('post', [['skip', M::condition('always')]]), 'A postConditionRule of the activity "s1" has no action that rule may take: it is left out.'];
        yield 'an unknown action' => [M::rules('exit', [['leave', M::condition('always')]]), 'A exitConditionRule of the activity "s1" has no action that rule may take: it is left out.'];
        yield 'a condition' => [M::rules('pre', [['skip', M::condition('sunny')]]), 'A rule of the activity "s1" tests a condition it may not: it is left out.'];
        yield 'no condition' => [M::rules('pre', [['skip', '']]), 'A rule of the activity "s1" has no condition: it is left out.'];
        yield 'no conditions element' => ['<imsss:sequencingRules><imsss:preConditionRule><imsss:ruleAction action="skip"/></imsss:preConditionRule></imsss:sequencingRules>', 'A rule of the activity "s1" has no condition: it is left out.'];
        yield 'a rollup condition' => ['<imsss:rollupRules>'.M::rollupRule('satisfied', M::rollupCondition('always')).'</imsss:rollupRules>', 'A rule of the activity "s1" tests a condition it may not: it is left out.'];
        yield 'a rollup action' => ['<imsss:rollupRules>'.M::rollupRule('passed', M::rollupCondition('satisfied')).'</imsss:rollupRules>', 'A rollup rule of the activity "s1" has no action or no child activity set it may take: it is left out.'];
        yield 'a child activity set' => ['<imsss:rollupRules>'.M::rollupRule('satisfied', M::rollupCondition('satisfied'), 'most').'</imsss:rollupRules>', 'A rollup rule of the activity "s1" has no action or no child activity set it may take: it is left out.'];
        yield 'no rollup conditions' => ['<imsss:rollupRules><imsss:rollupRule><imsss:rollupAction action="satisfied"/></imsss:rollupRule></imsss:rollupRules>', 'A rule of the activity "s1" has no condition: it is left out.'];
        yield 'an objective' => ['<imsss:objectives><imsss:primaryObjective objectiveID="a"/><imsss:objective objectiveID="a"/></imsss:objectives>', 'An objective of the activity "s1" has no identifier, or one used before: it is left out.'];
        yield 'an objective without identifier' => ['<imsss:objectives><imsss:objective/></imsss:objectives>', 'An objective of the activity "s1" has no identifier, or one used before: it is left out.'];
        yield 'a map' => ['<imsss:objectives><imsss:primaryObjective><imsss:mapInfo/></imsss:primaryObjective></imsss:objectives>', 'A map of an objective of the activity "s1" names no global objective: it is left out.'];
    }

    #[DataProvider('valuesSetAside')]
    public function testAValueTheReaderCannotUseIsNotedAndItsDefaultKept(string $sequencing, string $warning): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing($sequencing))));

        self::assertSame([$warning], $tree->warnings);
        self::assertSame([], $tree->get('s1')->sequencing->postConditionRules);
        self::assertSame([], $tree->get('s1')->sequencing->rollupRules);
        self::assertNull($tree->get('s1')->sequencing->limitConditions->attemptLimit);
        self::assertNull($tree->get('s1')->sequencing->limitConditions->attemptAbsoluteDurationLimit);
        self::assertFalse($tree->get('s1')->sequencing->controlMode->flow);
        self::assertSame(1.0, $tree->get('s1')->sequencing->rollupControls->measureWeight);
        self::assertSame(Requirement::Always, $tree->get('s1')->sequencing->rollupControls->requiredForCompleted);
        self::assertNull($tree->get('s1')->sequencing->randomizationControls->selectCount);
        self::assertSame(RandomizationTiming::Never, $tree->get('s1')->sequencing->randomizationControls->randomizationTiming);
        self::assertSame([], $tree->get('s1')->sequencing->primaryObjective()->maps);
    }

    public function testAnAttemptLimitOfZeroIsNoLimit(): void
    {
        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing('<imsss:limitConditions attemptLimit="0"/>'.M::rules('pre', [['skip', M::condition('objectiveMeasureLessThan', false, 'measureThreshold="2"')], ['skip', M::condition('sunny')]])))));

        self::assertNull($tree->get('s1')->sequencing->limitConditions->attemptLimit);
        self::assertEquals([new SequencingRule([new RuleCondition(ConditionType::ObjectiveMeasureLessThan)], RuleAction::Skip)], $tree->get('s1')->sequencing->preConditionRules);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function bounds(): iterable
    {
        yield 'rules' => [M::rules('pre', array_fill(0, SequencingReader::MAX_RULES + 1, ['skip', M::condition('always')])), 'The activity "s1" declares more than 32 rules of one kind.'];
        yield 'conditions' => [M::rules('pre', [['skip', str_repeat(M::condition('always'), SequencingReader::MAX_CONDITIONS + 1)]]), 'A rule of the activity "s1" holds more than 16 conditions.'];
        yield 'rollup rules' => ['<imsss:rollupRules>'.str_repeat(M::rollupRule('satisfied', M::rollupCondition('satisfied')), SequencingReader::MAX_RULES + 1).'</imsss:rollupRules>', 'The activity "s1" declares more than 32 rollup rules.'];
        yield 'objectives' => ['<imsss:objectives>'.str_repeat('<imsss:objective objectiveID="o"/>', SequencingReader::MAX_OBJECTIVES).'</imsss:objectives>', 'The activity "s1" declares more than 64 objectives.'];
        yield 'maps' => ['<imsss:objectives><imsss:primaryObjective>'.str_repeat('<imsss:mapInfo targetObjectiveID="g"/>', SequencingReader::MAX_MAPS + 1).'</imsss:primaryObjective></imsss:objectives>', 'An objective of the activity "s1" holds more than 16 maps.'];
    }

    #[DataProvider('bounds')]
    public function testTheRulesObjectivesAndMapsOfAnActivityAreBounded(string $sequencing, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing($sequencing))));
    }

    public function testTheBoundsJustBelowAreRead(): void
    {
        $sequencing = M::rules('pre', array_fill(0, SequencingReader::MAX_RULES, ['skip', str_repeat(M::condition('always'), SequencingReader::MAX_CONDITIONS)]))
            .'<imsss:rollupRules>'.str_repeat(M::rollupRule('satisfied', M::rollupCondition('satisfied')), SequencingReader::MAX_RULES).'</imsss:rollupRules>'
            .'<imsss:objectives><imsss:primaryObjective>'.str_repeat('<imsss:mapInfo targetObjectiveID="g"/>', SequencingReader::MAX_MAPS).'</imsss:primaryObjective>';

        for ($index = 1; $index < SequencingReader::MAX_OBJECTIVES; ++$index) {
            $sequencing .= '<imsss:objective objectiveID="o'.$index.'"/>';
        }

        $tree = new SequencingReader()->read(M::scorm2004(M::item('s1', M::sequencing($sequencing.'</imsss:objectives>'))));

        self::assertCount(SequencingReader::MAX_RULES, $tree->get('s1')->sequencing->preConditionRules);
        self::assertCount(SequencingReader::MAX_CONDITIONS, $tree->get('s1')->sequencing->preConditionRules[0]->conditions);
        self::assertCount(SequencingReader::MAX_RULES, $tree->get('s1')->sequencing->rollupRules);
        self::assertCount(SequencingReader::MAX_OBJECTIVES, $tree->get('s1')->sequencing->objectives);
        self::assertCount(SequencingReader::MAX_MAPS, $tree->get('s1')->sequencing->primaryObjective()->maps);
    }

    public function testTheManifestIsCheckedByTheManifestReader(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The SCORM manifest describes more than 1 items.');

        new SequencingReader(new PackageLimits(items: 1))->read(M::scorm2004(M::item('s1').M::item('s2')));
    }

    public function testTwoActivitiesMayNotShareAnIdentifier(): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The identifier "a" is used by two activities of the tree.');

        new ActivityTree(new Activity('root', 'Root', children: [new Activity('a', 'A'), new Activity('a', 'A again')]));
    }

    public function testTheRuleTimingsAndActionsMatch(): void
    {
        self::assertSame(RuleTiming::Pre, RuleAction::StopForwardTraversal->timing());
        self::assertSame(RuleTiming::Exit, RuleAction::Exit->timing());
        self::assertSame(RuleTiming::Post, RuleAction::Previous->timing());
        self::assertFalse(ConditionType::Always->isRollupCondition());
        self::assertFalse(ConditionType::ObjectiveMeasureLessThan->isRollupCondition());
        self::assertTrue(ConditionType::OutsideAvailableTimeRange->isRollupCondition());
        self::assertTrue(RollupAction::NotSatisfied->isObjective());
        self::assertFalse(RollupAction::Incomplete->isObjective());
        self::assertSame(Requirement::IfNotSkipped, new RollupControls(requiredForIncomplete: Requirement::IfNotSkipped)->requiredFor(RollupAction::Incomplete));
        self::assertFalse(new RandomizationControls(RandomizationTiming::Once)->isActive());
        self::assertFalse(new RandomizationControls(randomizationTiming: RandomizationTiming::Once)->isActive());
        self::assertTrue(new RandomizationControls(randomizationTiming: RandomizationTiming::OnEachNewAttempt, reorderChildren: true)->drawsOnEachAttempt());
        self::assertTrue(new RandomizationControls(RandomizationTiming::OnEachNewAttempt, 1)->drawsOnEachAttempt());
        self::assertFalse(new RandomizationControls(RandomizationTiming::Once, 1, RandomizationTiming::Once, true)->drawsOnEachAttempt());
        self::assertSame(Objective::PRIMARY, new Sequencing(objectives: [])->primaryObjective()->key());
    }
}
