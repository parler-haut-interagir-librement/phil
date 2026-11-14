<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Security;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Security\TenantRequestMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TenantRequestMatcher::class)]
final class TenantRequestMatcherTest extends TestCase
{
    public function testTheRequestsInItsTenantsMatch(): void
    {
        $context = new TenantContext();
        $matcher = new TenantRequestMatcher($context, ['rose', 'tavern']);
        $request = Request::create('https://shop.example/');

        self::assertFalse($matcher->matches($request), 'No tenant.');

        $context->enter(TenantId::fromString('rose'));
        self::assertTrue($matcher->matches($request));

        $context->enter(TenantId::fromString('tavern'));
        self::assertTrue($matcher->matches($request));

        $context->enter(TenantId::fromString('Rose'));
        self::assertFalse($matcher->matches($request));

        $context->enter(TenantId::fromString('general'));
        self::assertFalse($matcher->matches($request));
    }

    public function testAFirewallOfNoTenantIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A firewall of tenants needs at least one tenant.');

        new TenantRequestMatcher(new TenantContext(), []);
    }

    public function testAMalformedReferenceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new TenantRequestMatcher(new TenantContext(), ['rose', '']);
    }
}
