<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\RecordingActivation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantContext::class)]
#[CoversClass(NoActiveTenant::class)]
final class TenantContextTest extends TestCase
{
    public function testEnteringAndLeavingReachEveryActivation(): void
    {
        [$first, $second] = [new RecordingActivation(), new RecordingActivation()];
        $context = new TenantContext([$first, $second]);

        self::assertNull($context->current());

        $context->enter(TenantId::fromString('retail'));
        self::assertSame('retail', $context->current()?->value);
        self::assertSame('retail', $context->require()->value);

        $context->leave();
        self::assertNull($context->current());
        self::assertSame(['retail', '-'], $first->calls);
        self::assertSame(['retail', '-'], $second->calls);
    }

    public function testNoTenantIsRequiredInVain(): void
    {
        $this->expectException(NoActiveTenant::class);
        $this->expectExceptionMessageIsOrContains('No tenant is active in this request, message or command.');

        new TenantContext()->require();
    }

    public function testWorkWithinATenantReturnsToTheTenantBefore(): void
    {
        $activation = new RecordingActivation();
        $context = new TenantContext([$activation]);
        $context->enter(TenantId::fromString('retail'));

        self::assertSame('wholesale', $context->within(TenantId::fromString('wholesale'), static fn (): ?string => $context->current()?->value));
        self::assertSame('retail', $context->current()?->value);
        self::assertSame(['retail', 'wholesale', 'retail'], $activation->calls);
    }

    public function testWorkWithinATenantReturnsToNoneEvenWhenItFails(): void
    {
        $activation = new RecordingActivation();
        $context = new TenantContext([$activation]);

        try {
            $context->within(TenantId::fromString('wholesale'), static fn (): never => throw new RuntimeException('failed'));
        } catch (RuntimeException $runtimeException) {
            self::assertSame('failed', $runtimeException->getMessage());
        }

        self::assertNull($context->current());
        self::assertSame(['wholesale', '-'], $activation->calls);
    }
}
