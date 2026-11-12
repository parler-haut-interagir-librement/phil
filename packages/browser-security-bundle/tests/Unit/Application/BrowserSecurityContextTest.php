<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(BrowserSecurityContext::class)]
#[CoversClass(RequestState::class)]
final class BrowserSecurityContextTest extends TestCase
{
    public function testAnOverrideGoesToTheMainRequest(): void
    {
        $main = new Request();
        $stack = new RequestStack([$main]);
        $stack->push(new Request());

        $override = new ProtectionOverride(remove: ['Referrer-Policy', 'Content-Security-Policy']);

        new BrowserSecurityContext($stack)->override($override);

        self::assertSame([$override], RequestState::of($main)->protectionOverrides);
        self::assertSame([$override->policy], RequestState::of($main)->overrides);
        self::assertNull(RequestState::of($main)->protection(Protection::ReferrerPolicy, 'origin'));
        self::assertSame('DENY', RequestState::of($main)->protection(Protection::FrameOptions, 'DENY'));
    }

    public function testAnOverrideThatLeavesThePolicyAloneAddsNoPolicyOverride(): void
    {
        $state = new RequestState();
        $state->addProtectionOverride(new ProtectionOverride(remove: ['Referrer-Policy']));

        self::assertSame([], $state->overrides);
    }

    public function testWithoutARequestNothingHappens(): void
    {
        $this->expectNotToPerformAssertions();

        new BrowserSecurityContext(new RequestStack())->override(new ProtectionOverride());
    }

    public function testAServiceCannotChangeWhatActedBefore(): void
    {
        $this->expectException(InvalidPolicy::class);

        new BrowserSecurityContext(new RequestStack())->override(new ProtectionOverride(remove: ['https-redirect']));
    }
}
