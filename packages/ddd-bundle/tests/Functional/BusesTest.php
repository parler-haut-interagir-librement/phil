<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional;

use Phil\DddBundle\Infrastructure\Messenger\MessengerCommandBus;
use Phil\DddBundle\Infrastructure\Messenger\MessengerQueryBus;
use Phil\DddBundle\PhilDddBundle;
use Phil\DddBundle\Tests\Functional\App\Membership\CountMembers;
use Phil\DddBundle\Tests\Functional\App\Membership\EmailAlreadyRegistered;
use Phil\DddBundle\Tests\Functional\App\Membership\FindMemberEmail;
use Phil\DddBundle\Tests\Functional\App\Membership\MemberId;
use Phil\DddBundle\Tests\Functional\App\Membership\MemberRegistry;
use Phil\DddBundle\Tests\Functional\App\Membership\RegisterMember;
use Phil\DddBundle\Tests\Functional\App\Membership\UnknownMember;
use Phil\DddBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The two ports over the application's Messenger buses: a command handled on the command bus, a query answered
 * on the query bus, each handler placed on its bus by its marker alone, and the errors coming out as the caller
 * expects them.
 */
#[CoversClass(PhilDddBundle::class)]
#[CoversClass(MessengerCommandBus::class)]
#[CoversClass(MessengerQueryBus::class)]
final class BusesTest extends FunctionalTestCase
{
    public function testTheInterfacesResolveToTheMessengerAdapters(): void
    {
        self::assertInstanceOf(MessengerCommandBus::class, $this->commandBus());
        self::assertInstanceOf(MessengerQueryBus::class, $this->queryBus());
    }

    public function testACommandIsHandledAndAQueryReadsItsEffect(): void
    {
        $memberId = MemberId::generate();

        $this->commandBus()->dispatch(new RegisterMember($memberId->toString(), 'ada@example.com'));

        self::assertSame(1, $this->queryBus()->ask(new CountMembers()));
        self::assertSame('ada@example.com', $this->queryBus()->ask(new FindMemberEmail($memberId->toString())));
        self::assertCount(1, $this->service(MemberRegistry::class, MemberRegistry::class));
    }

    /**
     * Messenger wraps what the handler threw; the command bus hands the domain error back as is.
     */
    public function testADomainErrorComesOutOfTheCommandBusUnwrapped(): void
    {
        $this->commandBus()->dispatch(new RegisterMember(MemberId::generate()->toString(), 'ada@example.com'));

        $this->expectException(EmailAlreadyRegistered::class);
        $this->expectExceptionMessageIsOrContains('The e-mail address "ada@example.com" is already registered.');

        $this->commandBus()->dispatch(new RegisterMember(MemberId::generate()->toString(), 'ada@example.com'));
    }

    public function testWhatAQueryHandlerThrowsComesOutOfTheQueryBusUnwrapped(): void
    {
        $this->expectException(UnknownMember::class);

        $this->queryBus()->ask(new FindMemberEmail(MemberId::generate()->toString()));
    }

    /**
     * The marker decides the bus: the command handler is not on the query bus, so a command sent there finds no
     * handler rather than running without the command bus's middleware.
     */
    public function testACommandHandlerIsOnlyOnTheCommandBus(): void
    {
        $this->expectException(NoHandlerForMessageException::class);

        $this->service(TestKernel::QUERY_BUS, MessageBusInterface::class)->dispatch(new RegisterMember(MemberId::generate()->toString(), 'ada@example.com'));
    }

    public function testAQueryHandlerIsOnlyOnTheQueryBus(): void
    {
        $this->expectException(NoHandlerForMessageException::class);

        $this->service(TestKernel::COMMAND_BUS, MessageBusInterface::class)->dispatch(new CountMembers());
    }
}
