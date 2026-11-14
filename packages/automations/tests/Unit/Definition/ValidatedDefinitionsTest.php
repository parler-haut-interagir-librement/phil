<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Condition;
use Phil\Automations\Definition\DefinitionValidator;
use Phil\Automations\Definition\InMemoryDefinitions;
use Phil\Automations\Definition\Options;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Definition\ValidatedDefinitions;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Tests\Unit\Fixture\OrderPaidTrigger;
use Phil\Automations\Tests\Unit\Fixture\ScriptedAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidatedDefinitions::class)]
final class ValidatedDefinitionsTest extends TestCase
{
    public function testAValidDefinitionIsPublishedAndReadThrough(): void
    {
        $inner = new InMemoryDefinitions();
        $definitions = $this->definitions($inner);

        $definitions->publish($this->definition('a', "payload['plan'] == 'pro' and (outputs['a'] ?? null) == null and subject != null"));
        $definitions->publish($this->definition('b', 'true'), false);

        self::assertSame(['a'], array_map(static fn (AutomationDefinition $definition): string => $definition->id->value, [...$definitions->enabledFor('shop.order_paid')]));
        self::assertInstanceOf(AutomationDefinition::class, $definitions->get(AutomationId::fromString('b'), 1));
        self::assertInstanceOf(AutomationDefinition::class, $inner->get(AutomationId::fromString('a'), 1));
        self::assertNull($definitions->get(AutomationId::fromString('c'), 1));

        $definitions->disable(AutomationId::fromString('a'));
        self::assertSame([], [...$inner->enabledFor('shop.order_paid')]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenConditions(): iterable
    {
        yield 'not an expression' => ['payload[', 'does not read'];
        yield 'another variable' => ['user.admin', 'Variable "user" is not valid'];
        yield 'a function' => ["constant('PHP_VERSION') != ''", 'The function "constant" does not exist'];
    }

    #[DataProvider('brokenConditions')]
    public function testADefinitionWhoseConditionDoesNotReadIsNotKept(string $condition, string $problem): void
    {
        $inner = new InMemoryDefinitions();

        try {
            $this->definitions($inner)->publish($this->definition('a', $condition));
            self::fail('The definition was published.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertStringContainsString('Step "b": The condition "'.$condition.'" does not read', $invalidDefinition->getMessage());
            self::assertStringContainsString($problem, $invalidDefinition->getMessage());
        }

        self::assertNull($inner->get(AutomationId::fromString('a'), 1));
    }

    private function definitions(InMemoryDefinitions $inner): ValidatedDefinitions
    {
        return new ValidatedDefinitions($inner, new DefinitionValidator(new Catalogue([new OrderPaidTrigger()], [new ScriptedAction()], [new ExpressionConditions()])));
    }

    private function definition(string $id, string $condition): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString($id), 1, 'A', TriggerReference::of('shop.order_paid', Options::of(['plan' => 'pro'])), [
            Step::action('a', 'test.act', Options::of(['label' => 'x'])),
            Step::filter('b', Condition::expression($condition)),
        ]);
    }
}
