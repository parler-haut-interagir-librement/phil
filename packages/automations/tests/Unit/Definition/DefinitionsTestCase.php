<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Contract\Definitions;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Options;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\TriggerReference;
use PHPUnit\Framework\TestCase;

/**
 * Every promise of a definitions store.
 */
abstract class DefinitionsTestCase extends TestCase
{
    abstract protected function definitions(): Definitions;

    public function testThePublishedVersionOfEachEnabledAutomationStartsRuns(): void
    {
        $definitions = $this->definitions();
        $definitions->publish(self::definition('b', 1));
        $definitions->publish(self::definition('a', 1));
        $definitions->publish(self::definition('a', 2));
        $definitions->publish(self::definition('c', 1, 'other.trigger'));
        $definitions->publish(self::definition('d', 1), false);

        self::assertSame(['a:2', 'b:1'], self::names($definitions->enabledFor('shop.order_paid')));
        self::assertSame(['c:1'], self::names($definitions->enabledFor('other.trigger')));
        self::assertSame([], self::names($definitions->enabledFor('none')));
        self::assertEquals(self::definition('a', 1), $definitions->get(AutomationId::fromString('a'), 1));
        self::assertEquals(self::definition('d', 1), $definitions->get(AutomationId::fromString('d'), 1));
        self::assertNull($definitions->get(AutomationId::fromString('a'), 3));
        self::assertNull($definitions->get(AutomationId::fromString('z'), 1));
    }

    public function testADisabledAutomationStartsNoRunAndKeepsItsVersions(): void
    {
        $definitions = $this->definitions();
        $definitions->publish(self::definition('a', 1));
        $definitions->publish(self::definition('b', 1));

        $definitions->disable(AutomationId::fromString('a'));

        self::assertSame(['b:1'], self::names($definitions->enabledFor('shop.order_paid')));
        self::assertNotNull($definitions->get(AutomationId::fromString('a'), 1));

        $definitions->publish(self::definition('a', 1));
        self::assertSame(['a:1', 'b:1'], self::names($definitions->enabledFor('shop.order_paid')));

        $definitions->publish(self::definition('a', 2), false);
        self::assertSame(['b:1'], self::names($definitions->enabledFor('shop.order_paid')));
    }

    /**
     * @param iterable<AutomationDefinition> $definitions
     *
     * @return list<string>
     */
    protected static function names(iterable $definitions): array
    {
        $names = [];

        foreach ($definitions as $definition) {
            $names[] = $definition->id.':'.$definition->version;
        }

        sort($names);

        return $names;
    }

    protected static function definition(string $id, int $version, string $trigger = 'shop.order_paid'): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString($id), $version, 'Automation '.$id, TriggerReference::of($trigger, Options::of(['plan' => 'pro'])), [Step::action('one', 'test.act', Options::of(['n' => $version]), 2)]);
    }
}
