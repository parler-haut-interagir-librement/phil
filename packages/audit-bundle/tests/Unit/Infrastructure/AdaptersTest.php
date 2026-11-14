<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Message\AuditRecorded;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Infrastructure\Messenger\MessengerAuditNotifier;
use Phil\AuditBundle\Infrastructure\Security\SecurityActorResolver;
use Phil\AuditBundle\Infrastructure\Tenancy\TenancyTenantScope;
use Phil\AuditBundle\Infrastructure\Workflow\WorkflowAuditListener;
use Phil\AuditBundle\Tests\Unit\Fixture\ActorUser;
use Phil\AuditBundle\Tests\Unit\Fixture\AuditableOrder;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use Phil\AuditBundle\Tests\Unit\Fixture\PlainOrder;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

#[CoversClass(MessengerAuditNotifier::class)]
#[CoversClass(AuditRecorded::class)]
#[CoversClass(TenancyTenantScope::class)]
#[CoversClass(SecurityActorResolver::class)]
#[CoversClass(WorkflowAuditListener::class)]
final class AdaptersTest extends TestCase
{
    public function testARecordedRowIsDispatchedByItsPlaceOnly(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $sent = [];

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->sent[] = $message;

                return new Envelope($message, $stamps);
            }
        };

        $row = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable(), 'v1', '{"secret":"x"}', Facts::of('order.paid', 'acct-7'));
        new MessengerAuditNotifier($bus)->recorded($row);

        self::assertEquals([new AuditRecorded('orders', 1)], $bus->sent);
        self::assertSame(['segment' => 'orders', 'sequence' => 1], get_object_vars($bus->sent[0]), 'Nothing but the place travels.');
    }

    public function testTheTenantIsReadFromTheTenancyContext(): void
    {
        $context = new TenantContext();
        $scope = new TenancyTenantScope($context);

        self::assertNull($scope->current());
        $context->enter(TenantId::fromString('shop-a'));
        self::assertSame('shop-a', $scope->current());
        $context->leave();
        self::assertNull($scope->current());
    }

    public function testTheActorIsTheStableIdentifierOfTheUser(): void
    {
        $tokens = new TokenStorage();
        $actors = new SecurityActorResolver($tokens);

        self::assertNull($actors->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.org', null, ['ROLE_USER']), 'main', ['ROLE_USER']));
        self::assertNull($actors->current(), 'A user identifier is often an e-mail address: never taken.');

        $tokens->setToken(new UsernamePasswordToken(new ActorUser('acct-7'), 'main', ['ROLE_USER']));
        self::assertSame('acct-7', $actors->current());
    }

    public function testEachTransitionOfAnAuditableSubjectIsRecorded(): void
    {
        $rows = new InMemoryAuditRows();
        $trail = new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-04 12:00 UTC'));
        $actors = new class implements ActorResolver {
            #[Override]
            public function current(): string
            {
                return 'acct-7';
            }
        };
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.completed', new WorkflowAuditListener($trail, 'states', $actors));

        $definition = new Definition(['cart', 'paid', 'shipped'], [new Transition('Pay Now', 'cart', 'paid'), new Transition('ship', 'paid', 'shipped')]);
        $workflow = new Workflow($definition, new MethodMarkingStore(true, 'state'), $dispatcher, 'Order State');

        $order = new AuditableOrder('42');
        $workflow->apply($order, 'Pay Now');
        $workflow->apply($order, 'ship');

        $plain = new PlainOrder();
        $workflow->apply($plain, 'Pay Now');

        self::assertCount(2, $rows->rows['states']);
        $first = $rows->rows['states'][1];
        self::assertSame(['workflow.order_state.pay_now', 'acct-7', 'order', '42'], [$first->facts->action, $first->facts->actor, $first->facts->subject?->type, $first->facts->subject?->id]);
        self::assertSame('{"from":"cart","to":"paid","transition":"Pay Now","workflow":"Order State"}', $first->payload);
        self::assertSame('workflow.order_state.ship', $rows->rows['states'][2]->facts->action);

        $silent = new EventDispatcher();
        $silent->addListener('workflow.completed', new WorkflowAuditListener($trail, 'other'));
        new Workflow(new Definition(['a', 'b', 'c'], [new Transition('go', ['a'], ['b', 'c'])]), new MethodMarkingStore(false, 'places'), $silent, str_repeat('w', 70))->apply(new AuditableOrder('43'), 'go');

        $row = $rows->rows['other'][1];
        self::assertNull($row->facts->actor);
        self::assertSame(64, \strlen((string) $row->facts->action));
        self::assertSame('workflow.'.str_repeat('w', 55), $row->facts->action);
        self::assertSame('{"from":"a","to":"b,c","transition":"go","workflow":"'.str_repeat('w', 70).'"}', $row->payload);
    }
}
