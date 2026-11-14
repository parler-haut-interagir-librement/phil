<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Session;

use LogicException;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Session\SessionActiveTenantStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(SessionActiveTenantStore::class)]
final class SessionActiveTenantStoreTest extends TestCase
{
    public function testTheChoiceIsKeptInTheSessionOfTheMainRequest(): void
    {
        [$store, $session] = $this->store(previousSession: true);

        self::assertNull($store->remembered());

        $store->remember(TenantId::fromString('retail'));
        self::assertSame('retail', $session->get(SessionActiveTenantStore::KEY));
        self::assertSame('retail', $store->remembered()?->value);

        $store->forget();
        self::assertFalse($session->has(SessionActiveTenantStore::KEY));
        self::assertNull($store->remembered());
    }

    /**
     * A request without a session cookie is read without starting a session.
     */
    public function testARequestWithoutASessionCookieRemembersNothing(): void
    {
        [$store, $session] = $this->store(previousSession: false);
        $session->set(SessionActiveTenantStore::KEY, 'retail');

        self::assertNull($store->remembered());
    }

    public function testAValueThatIsNoLongerAReferenceIsDropped(): void
    {
        [$store, $session] = $this->store(previousSession: true);
        $session->set(SessionActiveTenantStore::KEY, str_repeat('x', 256));

        self::assertNull($store->remembered());
        self::assertFalse($session->has(SessionActiveTenantStore::KEY));

        $session->set(SessionActiveTenantStore::KEY, 42);
        self::assertNull($store->remembered());
        self::assertTrue($session->has(SessionActiveTenantStore::KEY));
    }

    public function testWithoutARequestNothingIsRememberedAndNothingCanBe(): void
    {
        $store = new SessionActiveTenantStore(new RequestStack());

        self::assertNull($store->remembered());
        $store->forget();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('There is no session to remember the active tenant in.');

        $store->remember(TenantId::fromString('retail'));
    }

    public function testARequestWithoutASessionCannotRemember(): void
    {
        $requests = new RequestStack([new Request()]);
        $store = new SessionActiveTenantStore($requests);

        self::assertNull($store->remembered());
        $store->forget();

        $this->expectException(LogicException::class);

        $store->remember(TenantId::fromString('retail'));
    }

    /**
     * The store reads the main request's session, never a sub-request's.
     *
     * @return array{SessionActiveTenantStore, Session}
     */
    private function store(bool $previousSession): array
    {
        $session = new Session(new MockArraySessionStorage());
        $main = new Request(cookies: $previousSession ? [$session->getName() => 'id'] : []);
        $main->setSession($session);

        $requests = new RequestStack([$main]);
        $requests->push(new Request());

        return [new SessionActiveTenantStore($requests), $session];
    }
}
