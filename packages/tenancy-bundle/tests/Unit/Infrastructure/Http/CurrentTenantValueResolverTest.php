<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Http;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Attribute\CurrentTenant;
use Phil\TenancyBundle\Infrastructure\Http\CurrentTenantValueResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(CurrentTenantValueResolver::class)]
#[CoversClass(CurrentTenant::class)]
final class CurrentTenantValueResolverTest extends TestCase
{
    public function testTheActiveTenantResolvesAMarkedArgument(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('retail'));

        $values = [...new CurrentTenantValueResolver($context)->resolve(new Request(), $this->argument(false))];

        self::assertCount(1, $values);
        self::assertSame('retail', $values[0]?->value);
    }

    public function testAnArgumentWithoutTheAttributeIsLeftToOtherResolvers(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('retail'));

        self::assertSame([], [...new CurrentTenantValueResolver($context)->resolve(new Request(), new ArgumentMetadata('tenant', TenantId::class, false, false, null))]);
    }

    public function testWithNoActiveTenantANullableArgumentIsNull(): void
    {
        self::assertSame([null], [...new CurrentTenantValueResolver(new TenantContext())->resolve(new Request(), $this->argument(true))]);
    }

    public function testWithNoActiveTenantARequiredArgumentIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('No tenant is active.');

        new CurrentTenantValueResolver(new TenantContext())->resolve(new Request(), $this->argument(false));
    }

    private function argument(bool $nullable): ArgumentMetadata
    {
        return new ArgumentMetadata('tenant', TenantId::class, false, false, null, $nullable, [new CurrentTenant()]);
    }
}
