<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Messenger;

use Closure;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Messenger\Attribute\AsMessageMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(TenantMiddleware::class)]
#[CoversClass(TenantStamp::class)]
final class TenantMiddlewareTest extends TestCase
{
    /** @var list<?string> the tenant active when the next middleware ran */
    private array $seen = [];

    public function testItGoesOnEveryBusBeforeTheDoctrineMiddleware(): void
    {
        $attributes = new ReflectionClass(TenantMiddleware::class)->getAttributes(AsMessageMiddleware::class);
        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();

        self::assertSame('*', $attribute->bus);
        self::assertSame(['doctrine_ping_connection', 'doctrine_close_connection', 'doctrine_open_transaction_logger', 'doctrine_transaction'], $attribute->before);
        self::assertNull($attribute->after);
    }

    public function testAMessageDispatchedInATenantIsStamped(): void
    {
        $context = $this->context('retail');

        $envelope = new TenantMiddleware($context)->handle(new Envelope(new stdClass()), $this->stack($context));

        self::assertSame('retail', $envelope->last(TenantStamp::class)?->tenant);
        self::assertCount(1, $envelope->all(TenantStamp::class));
    }

    public function testAStampAlreadyThereIsKept(): void
    {
        $context = $this->context('retail');

        $envelope = new TenantMiddleware($context)->handle(new Envelope(new stdClass(), [new TenantStamp('wholesale')]), $this->stack($context));

        self::assertSame(['wholesale'], array_map(static fn (TenantStamp $stamp): string => $stamp->tenant, $envelope->all(TenantStamp::class)));
        // Dispatching is not handling: the message is passed on in the dispatcher's tenant.
        self::assertSame(['retail'], $this->seen);
    }

    public function testAMessageDispatchedInNoTenantIsNotStamped(): void
    {
        $context = new TenantContext();

        $envelope = new TenantMiddleware($context)->handle(new Envelope(new stdClass()), $this->stack($context));

        self::assertNull($envelope->last(TenantStamp::class));
        self::assertSame([null], $this->seen);
    }

    public function testAReceivedMessageIsHandledInTheTenantOfItsStamp(): void
    {
        $context = $this->context('outlet');

        new TenantMiddleware($context)->handle(new Envelope(new stdClass(), [new TenantStamp('retail'), new ReceivedStamp('async')]), $this->stack($context));

        self::assertSame(['retail'], $this->seen);
        self::assertSame('outlet', $context->current()?->value);
    }

    public function testAReceivedMessageWithoutAStampIsHandledInTheWorkersTenant(): void
    {
        $context = new TenantContext();

        $envelope = new TenantMiddleware($context)->handle(new Envelope(new stdClass(), [new ReceivedStamp('async')]), $this->stack($context));

        self::assertSame([null], $this->seen);
        self::assertNull($envelope->last(TenantStamp::class));
    }

    private function context(string $tenant): TenantContext
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString($tenant));

        return $context;
    }

    /**
     * A stack whose next middleware records the tenant it runs in.
     */
    private function stack(TenantContext $context): StackInterface
    {
        $record = function () use ($context): void {
            $this->seen[] = $context->current()?->value;
        };

        $recorder = new readonly class($record) implements MiddlewareInterface {
            public function __construct(
                private Closure $record,
            ) {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->record)();

                return $envelope;
            }
        };

        // A bus hands the first middleware a stack that stands on it: `next()` is the second.
        return new StackMiddleware([new TenantMiddleware($context), $recorder]);
    }
}
