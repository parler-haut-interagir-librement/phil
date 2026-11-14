<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Http;

use Override;
use Phil\TenancyBundle\Application\Event\HostTenantResolved;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Http\ConfiguredHostTenants;
use Phil\TenancyBundle\Infrastructure\Http\ResolveHostTenant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ResolveHostTenant::class)]
#[CoversClass(HostTenantResolved::class)]
final class ResolveHostTenantTest extends TestCase
{
    public function testTheHostsTenantIsEnteredAndAnnounced(): void
    {
        $context = new TenantContext();
        $dispatcher = new EventDispatcher();
        $announced = [];
        $dispatcher->addListener(HostTenantResolved::class, static function (HostTenantResolved $event) use (&$announced): void {
            $announced[] = $event->host.'='.$event->tenant->value;
        });

        $this->listener($context, $dispatcher)($this->event(Request::create('https://Rose.Example/cart')));

        self::assertSame('rose', $context->current()?->value);
        self::assertSame(['rose.example=rose'], $announced);
    }

    public function testTheHostsTenantIsEnteredWithoutADispatcher(): void
    {
        $context = new TenantContext();

        $this->listener($context)($this->event(Request::create('https://rose.example/')));

        self::assertSame('rose', $context->current()?->value);
    }

    public function testAHostOfNoTenantLeavesTheRequestAlone(): void
    {
        $context = new TenantContext();

        $this->listener($context)($this->event(Request::create('https://elsewhere.example/')));

        self::assertNull($context->current());
    }

    public function testAHostServedWithoutTrustedHostsIsReported(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $records = [];

            #[Override]
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $values = [];

                foreach ($context as $key => $value) {
                    $values['{'.$key.'}'] = \is_string($value) ? $value : '';
                }

                $this->records[] = (\is_string($level) ? $level : '').': '.strtr((string) $message, $values);
            }
        };

        $listener = new ResolveHostTenant(new ConfiguredHostTenants(['rose.example' => 'rose']), new TenantContext(), logger: $logger);
        $listener($this->event(Request::create('https://rose.example/')));
        $listener($this->event(Request::create('https://elsewhere.example/')));

        self::assertSame(['warning: The host "rose.example" serves the tenant "rose", but framework.trusted_hosts is not set: any host a request names reaches the application. List the hosts of phil_tenancy.hosts in framework.trusted_hosts.'], $logger->records);

        // Once per process: the next request of the same worker is not reported again.
        $listener($this->event(Request::create('https://rose.example/again')));
        self::assertCount(1, $logger->records);

        Request::setTrustedHosts(['^rose\\.example$']);

        try {
            $listener($this->event(Request::create('https://rose.example/')));
        } finally {
            Request::setTrustedHosts([]);
        }

        self::assertCount(1, $logger->records);
    }

    public function testARequestWithTrustedHostsDoesNotSpendTheWarning(): void
    {
        $logger = new class extends AbstractLogger {
            public int $warnings = 0;

            #[Override]
            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->warnings;
            }
        };
        $listener = new ResolveHostTenant(new ConfiguredHostTenants(['rose.example' => 'rose']), new TenantContext(), logger: $logger);
        Request::setTrustedHosts(['^rose\\.example$']);

        try {
            $listener($this->event(Request::create('https://rose.example/')));
        } finally {
            Request::setTrustedHosts([]);
        }

        self::assertSame(0, $logger->warnings);

        $listener($this->event(Request::create('https://rose.example/')));
        $listener($this->event(Request::create('https://rose.example/')));

        self::assertSame(1, $logger->warnings);
    }

    public function testASubRequestIsNotResolvedAgain(): void
    {
        $context = new TenantContext();

        $this->listener($context)(new RequestEvent(self::createStub(HttpKernelInterface::class), Request::create('https://rose.example/'), HttpKernelInterface::SUB_REQUEST));

        self::assertNull($context->current());
    }

    private function listener(TenantContext $context, ?EventDispatcher $dispatcher = null): ResolveHostTenant
    {
        return new ResolveHostTenant(new ConfiguredHostTenants(['rose.example' => 'rose']), $context, $dispatcher);
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
