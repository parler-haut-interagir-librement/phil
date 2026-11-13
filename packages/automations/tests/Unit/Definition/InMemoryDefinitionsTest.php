<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Override;
use Phil\Automations\Contract\Definitions;
use Phil\Automations\Definition\InMemoryDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryDefinitions::class)]
final class InMemoryDefinitionsTest extends DefinitionsTestCase
{
    public function testDefinitionsGivenAtConstructionArePublished(): void
    {
        self::assertSame(['a:1'], self::names(new InMemoryDefinitions([self::definition('a', 1)])->enabledFor('shop.order_paid')));
    }

    #[Override]
    protected function definitions(): Definitions
    {
        return new InMemoryDefinitions();
    }
}
