<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Catalogue;

use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Exception\UnknownComponent;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Tests\Unit\Fixture\OrderPaid;
use Phil\Automations\Tests\Unit\Fixture\OrderPaidTrigger;
use Phil\Automations\Tests\Unit\Fixture\PlainAction;
use Phil\Automations\Tests\Unit\Fixture\ScriptedAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Catalogue::class)]
#[CoversClass(UnknownComponent::class)]
final class CatalogueTest extends TestCase
{
    public function testItFindsEachComponentByType(): void
    {
        $trigger = new OrderPaidTrigger();
        $action = new ScriptedAction();
        $plain = new PlainAction();
        $conditions = new ExpressionConditions();
        $catalogue = new Catalogue([$trigger], [$action, $plain], [$conditions]);

        self::assertSame($trigger, $catalogue->trigger('shop.order_paid'));
        self::assertSame($action, $catalogue->action('test.act'));
        self::assertSame($plain, $catalogue->action('test.plain'));
        self::assertSame($conditions, $catalogue->evaluator('expression'));
        self::assertSame([$trigger], $catalogue->triggersFor(new OrderPaid('1', 'c')));
        self::assertSame([], $catalogue->triggersFor(new stdClass()));
        self::assertSame(['shop.order_paid'], array_keys($catalogue->describeTriggers()));
        self::assertSame(['test.act', 'test.plain'], array_keys($catalogue->describeActions()));
        self::assertContainsOnlyInstancesOf(ComponentDescription::class, $catalogue->describeActions());
        self::assertSame('Plain', $catalogue->describeActions()['test.plain']->label);
        self::assertSame('Order paid', $catalogue->describeTriggers()['shop.order_paid']->label);
        self::assertSame(['expression'], $catalogue->languages());
    }

    public function testItSaysWhatIsNotRegistered(): void
    {
        $catalogue = new Catalogue();

        foreach ([
            'No trigger of type "x" is registered.' => static fn (): mixed => $catalogue->trigger('x'),
            'No action of type "x" is registered.' => static fn (): mixed => $catalogue->action('x'),
            'No condition evaluator reads the language "x".' => static fn (): mixed => $catalogue->evaluator('x'),
        ] as $message => $find) {
            try {
                $find();
                self::fail('Expected an unknown component.');
            } catch (UnknownComponent $unknown) {
                self::assertSame($message, $unknown->getMessage());
            }
        }
    }

    public function testItRefusesTwoTriggersOfOneType(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('Two of the registered components are the trigger "shop.order_paid".');

        new Catalogue([new OrderPaidTrigger(), new OrderPaidTrigger()]);
    }

    public function testItRefusesTwoActionsOfOneType(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('Two of the registered components are the action "test.act".');

        new Catalogue([], [new ScriptedAction(), new ScriptedAction()]);
    }

    public function testItRefusesTwoEvaluatorsOfOneLanguage(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('Two of the registered components are the condition language "expression".');

        new Catalogue([], [], [new ExpressionConditions(), new ExpressionConditions()]);
    }
}
