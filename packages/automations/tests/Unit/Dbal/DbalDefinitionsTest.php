<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Dbal;

use Override;
use Phil\Automations\Contract\Definitions;
use Phil\Automations\Dbal\DbalDefinitions;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Tests\Unit\Definition\DefinitionsTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DbalDefinitions::class)]
final class DbalDefinitionsTest extends DefinitionsTestCase
{
    public function testAVersionKeptNeverChanges(): void
    {
        $definitions = $this->definitions();
        $definitions->publish(self::definition('a', 1));

        $changed = new AutomationDefinition(AutomationId::fromString('a'), 1, 'Changed', TriggerReference::of('shop.order_paid'), [Step::delay('d', 1)]);

        try {
            $definitions->publish($changed);
            self::fail('Expected a refusal.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('Version 1 of "a" is kept with other steps: publish it as a new version.', $invalidDefinition->getMessage());
        }

        self::assertEquals(self::definition('a', 1), $definitions->get(AutomationId::fromString('a'), 1));
        self::assertSame(['a:1'], self::names($definitions->enabledFor('shop.order_paid')));
    }

    public function testTheSchemaIsAddedOnce(): void
    {
        $store = new DbalDefinitions(SqliteConnection::memory(), new MockClock(), 'definitions');
        $schema = $store->configureSchema(SqliteConnection::memory()->createSchemaManager()->introspectSchema());

        self::assertTrue($schema->hasTable('definitions'));
        self::assertSame($schema, $store->configureSchema($schema));
        self::assertCount(6, $schema->getTable('definitions')->getColumns());
    }

    #[Override]
    protected function definitions(): Definitions
    {
        $connection = SqliteConnection::memory();
        $store = new DbalDefinitions($connection, new MockClock('2026-11-13 10:00:00 UTC'));
        SqliteConnection::migrate($connection, $store->configureSchema(...));

        return $store;
    }
}
