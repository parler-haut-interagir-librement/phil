<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Phil\AccessBundle\Application\NoCurrentMember;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Infrastructure\Console\PurgeCommand;
use Phil\AccessBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\AccessBundle\Infrastructure\Session\SessionViewAsStore;
use Phil\AccessBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(TokenCurrentMember::class)]
#[CoversClass(SessionViewAsStore::class)]
#[CoversClass(PurgeCommand::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(NoCurrentMember::class)]
final class AdaptersTest extends TestCase
{
    public function testTheMemberOfTheToken(): void
    {
        self::assertNull(new TokenCurrentMember(new TokenStorage())->current());

        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('01JABC', null), 'main'));
        self::assertSame('01JABC', new TokenCurrentMember($tokens)->current()?->value);

        $email = new TokenStorage();
        $email->setToken(new UsernamePasswordToken(new InMemoryUser('ann@example.org', null), 'main'));
        $this->expectException(InvalidAccess::class);
        $this->expectExceptionMessageIsOrContains('never an email address');

        new TokenCurrentMember($email)->current();
    }

    public function testNobodyIsSignedIn(): void
    {
        self::assertNull(new NoCurrentMember()->current());
    }

    public function testTheViewInTheSession(): void
    {
        $outside = new SessionViewAsStore(new RequestStack());
        $outside->set(MemberId::of('ann'));
        self::assertNull($outside->get());

        $sessionless = new SessionViewAsStore(new RequestStack([Request::create('/')]));
        $sessionless->set(MemberId::of('ann'));
        self::assertNull($sessionless->get());

        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $store = new SessionViewAsStore(new RequestStack([$request]));
        self::assertNull($store->get());

        $store->set(MemberId::of('ann'));
        self::assertSame('ann', $session->get(SessionViewAsStore::ATTRIBUTE));
        self::assertSame('ann', $store->get()?->value);

        $store->set(null);
        self::assertFalse($session->has(SessionViewAsStore::ATTRIBUTE));
        self::assertNull($store->get());

        $session->set(SessionViewAsStore::ATTRIBUTE, 'ann@example.org');
        self::assertNull($store->get());
        $session->set(SessionViewAsStore::ATTRIBUTE, 42);
        self::assertNull($store->get());
    }

    public function testThePurge(): void
    {
        $stage = new Stage();
        $stage->writer->grant(ResourceReference::of('group', 'supporters'), 'member', ResourceReference::of('member', 'a'), $stage->clock->now()->modify('+1 hour'));
        $stage->writer->grant(ResourceReference::of('group', 'supporters'), 'member', ResourceReference::of('member', 'b'));
        $stage->clock->modify('+1 hour');

        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, new PurgeCommand($stage->store)(new SymfonyStyle(new ArrayInput([]), $output)));
        self::assertStringContainsString('1 expired tuples deleted.', $output->fetch());
    }

    public function testKitNames(): void
    {
        self::assertSame('Alert', new KitComponents()->name('Alert'));
        self::assertSame('ui:Alert:Title', new KitComponents('ui')->name('Alert:Title'));
        self::assertSame('ui:kit:Badge', new KitComponents('/ui/kit/')->name('Badge'));
    }
}
