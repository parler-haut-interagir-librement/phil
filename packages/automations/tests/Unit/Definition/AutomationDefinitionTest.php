<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\BranchPath;
use Phil\Automations\Definition\ComponentType;
use Phil\Automations\Definition\Condition;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepKind;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Exception\InvalidDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutomationDefinition::class)]
#[CoversClass(AutomationId::class)]
#[CoversClass(Step::class)]
#[CoversClass(Condition::class)]
#[CoversClass(TriggerReference::class)]
#[CoversClass(ComponentType::class)]
final class AutomationDefinitionTest extends TestCase
{
    public function testItWalksThroughBranches(): void
    {
        $definition = self::definition([
            Step::action('a', 'test.act'),
            Step::branch('b', Condition::expression('true'), [Step::action('c', 'test.act'), Step::branch('d', Condition::expression('true'), [Step::action('e', 'test.act')])], [Step::delay('f', 5)]),
            Step::branch('g', Condition::expression('true'), []),
        ]);

        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g'], array_map(static fn (Step $step): string => $step->id, $definition->allSteps()));
        self::assertSame('0', $definition->first()->toString());
        self::assertSame('a', $definition->stepAt(StepPath::fromString('0'))?->id);
        self::assertSame('e', $definition->stepAt(StepPath::fromString('1/then/1/then/0'))?->id);
        self::assertNull($definition->stepAt(StepPath::fromString('0/then/0')));
        self::assertNull($definition->stepAt(StepPath::fromString('9')));
        self::assertSame('1', $definition->after(StepPath::fromString('0'))?->toString());
        self::assertSame('1/then/1', $definition->after(StepPath::fromString('1/then/0'))?->toString());
        self::assertSame('2', $definition->after(StepPath::fromString('1/then/1/then/0'))?->toString());
        self::assertSame('2', $definition->after(StepPath::fromString('1/else/0'))?->toString());
        self::assertNull($definition->after(StepPath::fromString('2')));
        self::assertSame('1/else/0', $definition->enter(StepPath::fromString('1'), BranchPath::Else)?->toString());
        self::assertNull($definition->enter(StepPath::fromString('2'), BranchPath::Then));
        self::assertSame('2', $definition->enter(StepPath::fromString('1/then/1'), BranchPath::Else)?->toString());
    }

    public function testItKeepsItsParts(): void
    {
        $definition = self::definition([Step::action('a', 'test.act', retries: 10)], 'Name é');

        self::assertSame('welcome', (string) $definition->id);
        self::assertTrue($definition->id->equals(AutomationId::fromString('welcome')));
        self::assertFalse($definition->id->equals(AutomationId::fromString('other')));
        self::assertSame(1, $definition->version);
        self::assertSame('Name é', $definition->name);
        self::assertSame('shop.order_paid', $definition->trigger->type);
        self::assertSame([], $definition->trigger->options->values);
        self::assertSame(10, $definition->steps[0]->retries);
        self::assertSame(StepKind::Action, $definition->steps[0]->kind);
        self::assertSame([], Step::delay('x', 1)->path(BranchPath::Then));
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refused')]
    public function testItRefuses(callable $build, string $message): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refused(): iterable
    {
        yield 'a version 0' => [static fn (): AutomationDefinition => new AutomationDefinition(AutomationId::fromString('a'), 0, 'n', TriggerReference::of('t'), [Step::delay('d', 1)]), 'The version of a definition starts at 1.'];
        yield 'a blank name' => [static fn (): AutomationDefinition => self::definition([Step::delay('d', 1)], ' '), 'The name of a definition is not blank, at most 120 characters, without control characters.'];
        yield 'a long name' => [static fn (): AutomationDefinition => self::definition([Step::delay('d', 1)], str_repeat('é', 121)), 'The name of a definition is not blank'];
        yield 'a name with a control character' => [static fn (): AutomationDefinition => self::definition([Step::delay('d', 1)], "a\x7F"), 'The name of a definition is not blank'];
        yield 'no step' => [static fn (): AutomationDefinition => self::definition([]), 'A definition holds at least one step.'];
        yield 'two steps of one id' => [static fn (): AutomationDefinition => self::definition([Step::delay('d', 1), Step::branch('b', Condition::expression('true'), [Step::delay('d', 2)])]), 'Two steps share the id "d".'];
        yield 'too many steps' => [static fn (): AutomationDefinition => self::definition(array_map(static fn (int $i): Step => Step::delay('d'.$i, 1), range(0, 200))), 'A definition holds at most 200 steps.'];
        yield 'too deep' => [static fn (): AutomationDefinition => self::definition([self::nested(16)]), 'Branches nest at most 15 levels deep.'];
        yield 'a bad id' => [static fn (): AutomationId => AutomationId::fromString('Bad'), '"Bad" is not an automation id'];
        yield 'a bad step id' => [static fn (): Step => Step::delay('1a', 1), '"1a" is not a step id'];
        yield 'a delay of 0' => [static fn (): Step => Step::delay('d', 0), 'The delay "d" lasts between 1 second and 31622400 seconds (366 days).'];
        yield 'a long delay' => [static fn (): Step => Step::delay('d', 31_622_401), 'The delay "d" lasts between'];
        yield 'negative retries' => [static fn (): Step => Step::action('a', 'x', retries: -1), 'The step "a" retries between 0 and 10 times.'];
        yield 'too many retries' => [static fn (): Step => Step::action('a', 'x', retries: 11), 'The step "a" retries between 0 and 10 times.'];
        yield 'a bad type' => [static fn (): Step => Step::action('a', 'X.y'), '"X.y" is not a component type'];
        yield 'a long type' => [static fn (): TriggerReference => TriggerReference::of(str_repeat('a', 129)), 'is not a component type'];
        yield 'a type ending with a dot' => [static fn (): TriggerReference => TriggerReference::of('a.'), '"a." is not a component type'];
        yield 'a bad language' => [static fn (): Condition => Condition::of('Expr', 'true'), '"Expr" is not a condition language'];
        yield 'a blank condition' => [static fn (): Condition => Condition::of('expression', '  '), 'A condition is valid UTF-8, not blank, of at most 2048 bytes.'];
        yield 'a long condition' => [static fn (): Condition => Condition::of('expression', str_repeat('1', 2049)), 'A condition is valid UTF-8'];
        yield 'an invalid condition' => [static fn (): Condition => Condition::of('expression', "\xFF"), 'A condition is valid UTF-8'];
    }

    public function testItAcceptsItsBounds(): void
    {
        self::assertCount(200, self::definition(array_map(static fn (int $i): Step => Step::delay('d'.$i, 1), range(1, 200)))->allSteps());
        self::assertCount(16, self::definition([self::nested(15)])->allSteps());
        self::assertSame(31_622_400, Step::delay('d', 31_622_400)->seconds);
        self::assertSame(str_repeat('é', 120), self::definition([Step::delay('d', 1)], str_repeat('é', 120))->name);
        self::assertSame(128, \strlen(TriggerReference::of(str_repeat('a', 128))->type));
        self::assertSame('x.y_1.z', ComponentType::check('x.y_1.z'));
        self::assertSame(2048, \strlen(Condition::of('a', str_repeat('1', 2048))->source));
        self::assertSame('expression', Condition::expression('true')->language);
        self::assertSame('a'.str_repeat('0', 63), (string) AutomationId::fromString('a'.str_repeat('0', 63)));
    }

    /**
     * @param list<Step> $steps
     */
    private static function definition(array $steps, string $name = 'Welcome'): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString('welcome'), 1, $name, TriggerReference::of('shop.order_paid'), $steps);
    }

    private static function nested(int $levels): Step
    {
        $step = Step::delay('leaf', 1);

        for ($i = 0; $i < $levels; ++$i) {
            $step = Step::branch('b'.$i, Condition::expression('true'), [$step]);
        }

        return $step;
    }
}
