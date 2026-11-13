<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit;

use Phil\Automations\Attribute\AsAutomationAction;
use Phil\Automations\Attribute\AsAutomationTrigger;
use Phil\Automations\Exception\AutomationsException;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Exception\InvalidPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidDefinition::class)]
#[CoversClass(InvalidPayload::class)]
#[CoversClass(AsAutomationAction::class)]
#[CoversClass(AsAutomationTrigger::class)]
final class ExceptionsTest extends TestCase
{
    public function testTheExceptionsCarryTheirReason(): void
    {
        self::assertSame('why', InvalidDefinition::because('why')->getMessage());
        self::assertSame('why', InvalidPayload::because('why')->getMessage());
        self::assertInstanceOf(AutomationsException::class, InvalidPayload::because('why'));
    }

    public function testTheAttributesNameTheirTag(): void
    {
        self::assertSame('phil.automations.action', AsAutomationAction::TAG);
        self::assertSame('phil.automations.trigger', AsAutomationTrigger::TAG);
        self::assertInstanceOf(AsAutomationAction::class, new AsAutomationAction());
        self::assertInstanceOf(AsAutomationTrigger::class, new AsAutomationTrigger());
    }
}
