<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\FailingActivation;
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

    /**
     * An activation that fails after another leaves no tenant half-entered: every activation is left, the failure
     * comes through, and the context holds no tenant.
     */
    public function testAnActivationThatFailsAfterAnotherLeavesNoTenant(): void
    {
        [$before, $failing, $after] = [new RecordingActivation(), new FailingActivation(['wholesale']), new RecordingActivation()];
        $context = new TenantContext([$before, $failing, $after]);

        try {
            $context->enter(TenantId::fromString('wholesale'));
            self::fail('Entering must fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('cannot enter wholesale', $runtimeException->getMessage());
        }

        self::assertNull($context->current());
        self::assertSame(['wholesale', '-'], $before->calls);
        self::assertSame(['wholesale', '-'], $failing->calls);
        self::assertSame(['-'], $after->calls, 'never entered, left all the same');
    }

    public function testWorkWithinATenantThatCannotBeEnteredReturnsToTheTenantBefore(): void
    {
        $activation = new RecordingActivation();
        $context = new TenantContext([$activation, new FailingActivation(['wholesale'])]);
        $context->enter(TenantId::fromString('retail'));

        $ran = false;

        try {
            $context->within(TenantId::fromString('wholesale'), static function () use (&$ran): void {
                $ran = true;
            });
            self::fail('Entering must fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('cannot enter wholesale', $runtimeException->getMessage());
        }

        self::assertFalse($ran);
        self::assertSame('retail', $context->current()?->value);
        self::assertSame(['retail', 'wholesale', '-', 'retail'], $activation->calls);
    }

    /**
     * Leaving reaches every activation even when one fails before the others; the first failure comes through.
     */
    public function testLeavingReachesEveryActivationWhenOneFails(): void
    {
        [$first, $second, $last] = [new FailingActivation(failsOnLeaving: true, leaveMessage: 'first cannot leave'), new FailingActivation(failsOnLeaving: true, leaveMessage: 'second cannot leave'), new RecordingActivation()];
        $context = new TenantContext([$first, $second, $last]);
        $context->enter(TenantId::fromString('retail'));

        try {
            $context->leave();
            self::fail('Leaving must fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('first cannot leave', $runtimeException->getMessage());
            self::assertNull($runtimeException->getPrevious());
        }

        self::assertNull($context->current());
        self::assertSame(['retail', '-'], $first->calls);
        self::assertSame(['retail', '-'], $second->calls);
        self::assertSame(['retail', '-'], $last->calls);
    }
}
