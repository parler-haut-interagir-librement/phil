<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Http\FlashToasts;
use Phil\NotificationsBundle\Infrastructure\Http\NullToasts;
use Phil\NotificationsBundle\Infrastructure\Mercure\MercureToasts;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Current;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Logs;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingHub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(MercureToasts::class)]
#[CoversClass(FlashToasts::class)]
#[CoversClass(NullToasts::class)]
final class ToastsTest extends TestCase
{
    public function testMercurePushesTheToastToTheRecipientsTopic(): void
    {
        $hub = new RecordingHub();
        $toasts = new MercureToasts($hub);
        $toasts->publish($this->toast(), 3);

        self::assertCount(1, $hub->updates);
        self::assertSame(['urn:phil:notifications:42'], $hub->updates[0]->getTopics());
        self::assertTrue($hub->updates[0]->isPrivate());
        self::assertSame('{"unread":3,"toast":{"id":"n1","type":"welcome","title":"Hello","body":"World","url":"\/a","count":1}}', $hub->updates[0]->getData());
        self::assertSame('urn:phil:notifications:42', $toasts->topic('42'));
    }

    public function testMercureMayBePublicUnderAnotherPrefix(): void
    {
        $hub = new RecordingHub();
        new MercureToasts($hub, 'urn:site:', false)->publish($this->toast(), 0);

        self::assertSame(['urn:site:42'], $hub->updates[0]->getTopics());
        self::assertFalse($hub->updates[0]->isPrivate());
    }

    public function testAHubThatCannotBeReachedIsLogged(): void
    {
        $logs = new Logs();
        new MercureToasts(new RecordingHub(failing: true), logger: $logs)->publish($this->toast(), 1);
        new MercureToasts(new RecordingHub(failing: true))->publish($this->toast(), 1);

        self::assertSame(['warning: The toast of "42" could not be pushed to Mercure: The hub is down.'], $logs->records);
    }

    public function testAHubThatCannotPublishOutsideARequestIsLogged(): void
    {
        $logs = new Logs();
        $hub = new RecordingHub(failure: new RuntimeException('The mercure_publish() function is not available.'));
        new MercureToasts($hub, logger: $logs)->publish($this->toast(), 1);

        self::assertSame([], $hub->updates);
        self::assertSame(['warning: The toast of "42" could not be pushed to Mercure: The mercure_publish() function is not available.'], $logs->records);
    }

    public function testTheSessionKeepsTheToastsOfTheCurrentRecipient(): void
    {
        [$requests, $session] = $this->requests();
        $toasts = new FlashToasts($requests, new Current('42'));
        $toasts->publish($this->toast(), 1);
        $toasts->publish($this->toast('43'), 1);
        $toasts->publish($this->toast(), 2);

        $toast = ['id' => 'n1', 'type' => 'welcome', 'title' => 'Hello', 'body' => 'World', 'url' => '/a', 'count' => 1];
        self::assertSame([$toast, $toast], $toasts->drain());
        self::assertSame([], $toasts->drain());
        self::assertFalse($session->getFlashBag()->has(FlashToasts::FLASH_TYPE));
    }

    public function testADrainedToastIsChecked(): void
    {
        [$requests, $session] = $this->requests();
        $bag = $session->getFlashBag();
        $bag->add(FlashToasts::FLASH_TYPE, 'nonsense');
        $bag->add(FlashToasts::FLASH_TYPE, ['id' => 'n1']);
        $bag->add(FlashToasts::FLASH_TYPE, ['title' => 'T']);
        $bag->add(FlashToasts::FLASH_TYPE, ['id' => 'n2', 'title' => 'T', 'type' => 5, 'body' => 5, 'url' => 5, 'count' => 'x']);

        self::assertSame([['id' => 'n2', 'type' => '', 'title' => 'T', 'body' => '', 'url' => null, 'count' => 1]], new FlashToasts($requests, new Current())->drain());
    }

    public function testWithoutRequestOrSessionNothingIsKept(): void
    {
        $empty = new FlashToasts(new RequestStack(), new Current());
        $empty->publish($this->toast(), 1);
        self::assertSame([], $empty->drain());

        $requests = new RequestStack([new Request()]);
        $sessionless = new FlashToasts($requests, new Current());
        $sessionless->publish($this->toast(), 1);
        self::assertSame([], $sessionless->drain());
        $request = new Request();
        $request->setSession(self::createStub(SessionInterface::class));

        $requests = new RequestStack([$request]);
        $plain = new FlashToasts($requests, new Current());
        $plain->publish($this->toast(), 1);
        self::assertSame([], $plain->drain());
    }

    public function testAVisitorGetsNoToast(): void
    {
        [$requests] = $this->requests();
        $toasts = new FlashToasts($requests, new Current(null));
        $toasts->publish($this->toast(), 1);

        self::assertSame([], $toasts->drain());
    }

    public function testTheNullPublisherShowsNothing(): void
    {
        $toasts = new NullToasts();
        $toasts->publish($this->toast(), 1);

        self::assertInstanceOf(NullToasts::class, $toasts);
    }

    /**
     * @return array{RequestStack, Session}
     */
    private function requests(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requests = new RequestStack([$request]);

        return [$requests, $session];
    }

    private function toast(string $recipient = '42'): RenderedNotification
    {
        return new RenderedNotification(new Notification('n1', 'welcome', $recipient, null, new DateTimeImmutable()), 'Hello', 'World', '/a');
    }
}
