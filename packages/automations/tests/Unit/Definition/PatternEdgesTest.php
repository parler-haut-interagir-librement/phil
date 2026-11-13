<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Dbal\Sql;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Condition;
use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionWriter;
use Phil\Automations\Definition\Options;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Exception\InvalidPayload;
use Phil\Automations\Run\RunId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each name and text rule is anchored at both ends, without letting a trailing new line through, and reads UTF-8.
 */
#[CoversClass(Step::class)]
#[CoversClass(AutomationId::class)]
#[CoversClass(Condition::class)]
#[CoversClass(Options::class)]
#[CoversClass(OptionSpec::class)]
#[CoversClass(RunId::class)]
#[CoversClass(Sql::class)]
#[CoversClass(AutomationDefinition::class)]
#[CoversClass(StepPath::class)]
#[CoversClass(TriggerReference::class)]
#[CoversClass(DefinitionWriter::class)]
#[CoversClass(DefinitionReader::class)]
final class PatternEdgesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function strays(): iterable
    {
        yield 'a prefix' => ['!'];
        yield 'a suffix' => ['!'];
        yield 'a trailing new line' => ["\n"];
    }

    #[DataProvider('strays')]
    public function testAStepIdIsAnchoredAtBothEnds(string $stray): void
    {
        self::assertSame('ab', Step::filter('ab', Condition::expression('true'))->id);

        $this->expectException(InvalidDefinition::class);
        Step::filter('ab'.$stray, Condition::expression('true'));
    }

    public function testAStepIdHasNoPrefix(): void
    {
        $this->expectException(InvalidDefinition::class);
        Step::filter('!ab', Condition::expression('true'));
    }

    #[DataProvider('strays')]
    public function testAnAutomationIdIsAnchoredAtBothEnds(string $stray): void
    {
        self::assertSame('ab', AutomationId::fromString('ab')->value);

        $this->expectException(InvalidDefinition::class);
        AutomationId::fromString('ab'.$stray);
    }

    public function testAnAutomationIdHasNoPrefix(): void
    {
        $this->expectException(InvalidDefinition::class);
        AutomationId::fromString('!ab');
    }

    #[DataProvider('strays')]
    public function testAConditionLanguageIsAnchoredAtBothEnds(string $stray): void
    {
        self::assertSame('ab', Condition::of('ab', 'x')->language);

        $this->expectException(InvalidDefinition::class);
        Condition::of('ab'.$stray, 'x');
    }

    public function testAConditionLanguageHasNoPrefix(): void
    {
        $this->expectException(InvalidDefinition::class);
        Condition::of('!ab', 'x');
    }

    #[DataProvider('strays')]
    public function testAnOptionNameIsAnchoredAtBothEnds(string $stray): void
    {
        self::assertSame(['ab' => 1], Options::of(['ab' => 1])->values);

        $this->expectException(InvalidDefinition::class);
        Options::of(['ab'.$stray => 1]);
    }

    public function testAnOptionNameHasNoPrefix(): void
    {
        $this->expectException(InvalidDefinition::class);
        Options::of(['!ab' => 1]);
    }

    #[DataProvider('strays')]
    public function testAnIdentifierOptionIsAnchoredAtBothEnds(string $stray): void
    {
        $spec = new OptionSpec('who', OptionType::Identifier);

        self::assertNull($spec->problem(Options::of(['who' => 'a-b'])));
        self::assertNotNull($spec->problem(Options::of(['who' => 'a-b'.$stray])));
    }

    public function testAnIdentifierOptionHasNoPrefix(): void
    {
        self::assertNotNull(new OptionSpec('who', OptionType::Identifier)->problem(Options::of(['who' => '!ab'])));
    }

    #[DataProvider('strays')]
    public function testARunIdIsAnchoredAtBothEnds(string $stray): void
    {
        $id = str_repeat('a1', 16);
        self::assertSame($id, RunId::fromString($id)->value);

        $this->expectException(InvalidPayload::class);
        RunId::fromString($id.$stray);
    }

    public function testARunIdHasNoPrefix(): void
    {
        $this->expectException(InvalidPayload::class);
        RunId::fromString('!'.str_repeat('a1', 16));
    }

    public function testARunIdIsDrawnFromTheAutomationAndTheEventKey(): void
    {
        $id = RunId::for(AutomationId::fromString('welcome'), 'order:42');

        self::assertSame(substr(hash('sha256', "welcome\0order:42"), 0, 32), $id->value);
        self::assertSame(32, \strlen($id->value));
    }

    #[DataProvider('strays')]
    public function testATableNameIsAnchoredAtBothEnds(string $stray): void
    {
        self::assertSame('runs_1', Sql::tableName('runs_1'));

        $this->expectException(InvalidDefinition::class);
        Sql::tableName('runs'.$stray);
    }

    public function testATableNameHasNoPrefix(): void
    {
        $this->expectException(InvalidDefinition::class);
        Sql::tableName('!runs');
    }

    public function testTheStepsKeepTheirDefaults(): void
    {
        $condition = Condition::expression('true');
        $options = Options::of(['label' => 'x']);

        $action = Step::action('a', 'test.act');
        self::assertSame(0, $action->retries);
        self::assertSame(0, $action->seconds);
        self::assertSame([], $action->options->values);
        self::assertSame($options, Step::action('b', 'test.act', $options)->options);
        self::assertSame(3, Step::action('c', 'test.act', null, 3)->retries);
        self::assertSame(0, Step::delay('d', 5)->retries);
        self::assertSame(0, Step::filter('e', $condition)->retries);
        self::assertSame(0, Step::filter('e', $condition)->seconds);
        self::assertSame(0, Step::branch('f', $condition, [])->retries);
        self::assertSame(0, Step::branch('f', $condition, [])->seconds);
    }

    public function testADefinitionNameIsValidUtf8(): void
    {
        $trigger = TriggerReference::of('shop.order_paid', Options::none());
        $steps = [Step::filter('a', Condition::expression('true'))];

        self::assertSame('Café', new AutomationDefinition(AutomationId::fromString('x'), 1, 'Café', $trigger, $steps)->name);

        $this->expectException(InvalidDefinition::class);
        new AutomationDefinition(AutomationId::fromString('x'), 1, "bad\xFF", $trigger, $steps);
    }

    public function testASeekIntoAMissingStepFindsNothing(): void
    {
        $definition = new AutomationDefinition(AutomationId::fromString('x'), 1, 'X', TriggerReference::of('shop.order_paid', Options::none()), [Step::filter('a', Condition::expression('true'))]);

        self::assertNull($definition->stepAt(StepPath::fromString('5/then/0')));
        self::assertNull($definition->stepAt(StepPath::fromString('0/else/0')));
    }

    public function testAStepPathReadsItsIndexAndItsBranches(): void
    {
        self::assertSame(3, StepPath::fromString('3/then/1')->index);
        self::assertSame(3, StepPath::fromString('3')->index);
        self::assertCount(2, StepPath::fromString('3/then/1/else/2')->nested);
    }

    public function testADefinitionIsWrittenWithItsSlashesAndLettersUnescaped(): void
    {
        $definition = new AutomationDefinition(AutomationId::fromString('x'), 1, 'Café/é', TriggerReference::of('shop.order_paid', Options::none()), [Step::action('a', 'test.act', Options::of(['label' => 'à/b']))]);

        $json = new DefinitionWriter()->write($definition);

        self::assertStringContainsString('"name":"Café/é"', $json);
        self::assertStringContainsString('"label":"à/b"', $json);
    }

    public function testADefinitionOfTheLimitSizeIsRead(): void
    {
        $reader = new DefinitionReader();
        $json = '{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid"},"steps":[{"id":"a","type":"filter","condition":{"source":"true"}}]}';
        $padded = $json.str_repeat(' ', DefinitionReader::MAX_BYTES - \strlen($json));

        self::assertSame(DefinitionReader::MAX_BYTES, \strlen($padded));
        self::assertSame('x', $reader->read($padded)->id->value);

        $this->expectException(InvalidDefinition::class);
        $reader->read($padded.' ');
    }

    public function testAnEmptyStepsListInsideABranchStartsAtTheNextDepth(): void
    {
        $reader = new DefinitionReader();
        $branch = static fn (string $id, string $inner): string => \sprintf('{"id":"%s","type":"branch","condition":{"source":"true"},"then":%s}', $id, $inner);
        $chain = '[]';

        for ($i = 15; $i >= 1; --$i) {
            $chain = '['.$branch('b'.$i, $chain).']';
        }

        $head = '{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid"},"steps":';

        self::assertCount(15, $reader->read($head.$chain.'}')->allSteps());

        $deeper = '['.$branch('b0', $chain).']';
        $this->expectException(InvalidDefinition::class);
        $reader->read($head.$deeper.'}');
    }

    public function testTheElsePathCountsOneDepthDeeperToo(): void
    {
        $reader = new DefinitionReader();
        $chain = '[]';

        for ($i = 15; $i >= 1; --$i) {
            $chain = \sprintf('[{"id":"b%d","type":"branch","condition":{"source":"true"},"then":[{"id":"t%d","type":"filter","condition":{"source":"true"}}],"else":%s}]', $i, $i, $chain);
        }

        $head = '{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid"},"steps":';

        self::assertCount(30, $reader->read($head.$chain.'}')->allSteps());

        $deeper = \sprintf('[{"id":"b0","type":"branch","condition":{"source":"true"},"then":[{"id":"t0","type":"filter","condition":{"source":"true"}}],"else":%s}]', $chain);
        $this->expectException(InvalidDefinition::class);
        $reader->read($head.$deeper.'}');
    }

    public function testTheStepsOfAMaximumListAreAcceptedButNotOneMore(): void
    {
        $reader = new DefinitionReader();
        $head = '{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid"},"steps":';
        $steps = static fn (int $count): string => '['.implode(',', array_map(static fn (int $i): string => \sprintf('{"id":"s%d","type":"filter","condition":{"source":"true"}}', $i), range(1, $count))).']';

        self::assertCount(200, $reader->read($head.$steps(200).'}')->allSteps());

        $this->expectException(InvalidDefinition::class);
        $reader->read($head.$steps(201).'}');
    }

    public function testTheKeysOfEachObjectAreRequiredAndTheMessagesNameWhatIsWrong(): void
    {
        $reader = new DefinitionReader();
        $trigger = '"trigger":{"type":"shop.order_paid"}';
        $filter = '{"id":"a","type":"filter","condition":{"source":"true"}}';

        try {
            $reader->read('{"version":1,"name":"X",'.$trigger.',"steps":['.$filter.']}');
            self::fail('A definition without its id is refused.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('The definition needs the key "id".', $invalidDefinition->getMessage());
        }

        // The id of a step is read before the keys are checked, so a missing one is named by its own message.
        $steps = [
            'action' => '{"type":"action","action":"test.act"}',
            'delay' => '{"type":"delay","seconds":5}',
            'filter' => '{"type":"filter","condition":{"source":"true"}}',
            'branch' => '{"type":"branch","condition":{"source":"true"},"then":[]}',
        ];

        foreach ($steps as $kind => $step) {
            try {
                $reader->read('{"id":"x","version":1,"name":"X",'.$trigger.',"steps":['.$step.']}');
                self::fail('A '.$kind.' without its id is refused.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame('The id of a step is a string.', $invalid->getMessage());
            }
        }
    }

    public function testTheMessagesOfTheMistakesInsideAStepNameTheStep(): void
    {
        $reader = new DefinitionReader();
        $head = '{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid"},"steps":[';
        $cases = [
            'The action of the step "a" is a string.' => '{"id":"a","type":"action","action":5}',
            'The retries of the step "a" is an integer.' => '{"id":"a","type":"action","action":"test.act","retries":"2"}',
            'The seconds of the step "a" is an integer.' => '{"id":"a","type":"delay","seconds":"5"}',
            'The language of the step "a" is a string.' => '{"id":"a","type":"filter","condition":{"language":5,"source":"true"}}',
            'The condition of the step "a" is a string.' => '{"id":"a","type":"filter","condition":{"source":5}}',
            'The condition of the step "a" is a JSON object.' => '{"id":"a","type":"filter","condition":[1]}',
            'The step "a" has no key "extra".' => '{"id":"a","type":"filter","condition":{"source":"true"},"extra":1}',
            'The condition of the step "a" has no key "extra".' => '{"id":"a","type":"filter","condition":{"source":"true","extra":1}}',
            'The condition of the step "a" needs the key "source".' => '{"id":"a","type":"filter","condition":{"language":"expression"}}',
            'The step "a" needs the key "seconds".' => '{"id":"a","type":"delay"}',
            'The step "a" needs the key "action".' => '{"id":"a","type":"action"}',
            'The step "a" needs the key "condition".' => '{"id":"a","type":"filter"}',
            'The step "a" needs the key "then".' => '{"id":"a","type":"branch","condition":{"source":"true"}}',
        ];

        foreach ($cases as $message => $step) {
            try {
                $reader->read($head.$step.']}');
                self::fail('"'.$message.'" is expected.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testTheLanguageOfAConditionDefaultsToTheExpressionLanguage(): void
    {
        $definition = new DefinitionReader()->read('{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid"},"steps":[{"id":"a","type":"filter","condition":{"source":"true"}},{"id":"b","type":"filter","condition":{"language":"other","source":"true"}}]}');

        self::assertSame('expression', $definition->steps[0]->condition?->language);
        self::assertSame('other', $definition->steps[1]->condition?->language);
    }

    public function testAnEmptyListOfOptionsIsNone(): void
    {
        $definition = new DefinitionReader()->read('{"id":"x","version":1,"name":"X","trigger":{"type":"shop.order_paid","options":[]},"steps":[{"id":"a","type":"action","action":"test.act","options":[]}]}');

        self::assertSame([], $definition->trigger->options->values);
        self::assertSame([], $definition->steps[0]->options->values);
    }

    public function testADefinitionIsReadFromAnArrayToo(): void
    {
        $definition = new DefinitionReader()->fromArray(['id' => 'x', 'version' => 1, 'name' => 'X', 'trigger' => ['type' => 'shop.order_paid'], 'steps' => [['id' => 'a', 'type' => 'filter', 'condition' => ['source' => 'true']]]]);

        self::assertSame('x', $definition->id->value);
    }

    public function testATriggerKeepsItsOptions(): void
    {
        $options = Options::of(['plan' => 'pro']);

        self::assertSame($options, TriggerReference::of('shop.order_paid', $options)->options);
        self::assertSame([], TriggerReference::of('shop.order_paid')->options->values);
    }
}
