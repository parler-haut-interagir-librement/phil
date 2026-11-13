<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Condition;
use Phil\Automations\Definition\DefinitionValidator;
use Phil\Automations\Definition\Options;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Tests\Unit\Fixture\OrderPaidTrigger;
use Phil\Automations\Tests\Unit\Fixture\ScriptedAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefinitionValidator::class)]
final class DefinitionValidatorTest extends TestCase
{
    public function testAValidDefinitionHasNoProblem(): void
    {
        $validator = $this->validator();
        $definition = $this->definition('shop.order_paid', ['plan' => 'pro'], [
            Step::action('a', 'test.act', Options::of(['label' => 'x'])),
            Step::branch('b', Condition::expression("payload['plan'] == 'pro'"), [Step::delay('c', 5)]),
        ]);

        self::assertSame([], $validator->problems($definition));
        $validator->validate($definition);
    }

    public function testItListsEveryProblem(): void
    {
        $definition = $this->definition('shop.order_paid', ['plan' => 'gold'], [
            Step::action('a', 'test.act', Options::of(['other' => 1])),
            Step::action('b', 'test.unknown'),
            Step::filter('c', Condition::of('rules', 'x')),
            Step::branch('d', Condition::expression('payload['), [Step::filter('e', Condition::expression('nope'))]),
        ]);

        $problems = $this->validator()->problems($definition);

        self::assertSame('The option "plan" is not a valid choice.', $problems[0]);
        self::assertSame('Step "a": "test.act" takes no option "other".', $problems[1]);
        self::assertSame('Step "b": No action of type "test.unknown" is registered.', $problems[2]);
        self::assertSame('Step "c": No condition evaluator reads the language "rules".', $problems[3]);
        self::assertStringStartsWith('Step "d": The condition "payload[" does not read:', $problems[4]);
        self::assertStringStartsWith('Step "e": The condition "nope" does not read:', $problems[5]);
        self::assertCount(6, $problems);
    }

    public function testAnUnknownTriggerIsAProblem(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('No trigger of type "x" is registered. Step "a": No action of type "y" is registered.');

        $this->validator()->validate($this->definition('x', [], [Step::action('a', 'y')]));
    }

    private function validator(): DefinitionValidator
    {
        return new DefinitionValidator(new Catalogue([new OrderPaidTrigger()], [new ScriptedAction()], [new ExpressionConditions()]));
    }

    /**
     * @param array<string, string> $options
     * @param list<Step>            $steps
     */
    private function definition(string $trigger, array $options, array $steps): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString('a'), 1, 'A', TriggerReference::of($trigger, Options::of($options)), $steps);
    }
}
