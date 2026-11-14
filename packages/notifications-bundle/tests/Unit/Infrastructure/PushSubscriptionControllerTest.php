<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\NotificationsBundle\Application\WebPush\PushSubscriber;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\PushSubscriptionController;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryPushSubscriptions;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Current;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(PushSubscriptionController::class)]
final class PushSubscriptionControllerTest extends TestCase
{
    private InMemoryPushSubscriptions $subscriptions;

    private Current $current;

    #[Override]
    protected function setUp(): void
    {
        $this->subscriptions = new InMemoryPushSubscriptions();
        $this->current = new Current('42');
    }

    public function testABrowserIsSubscribedThenUnsubscribed(): void
    {
        $response = $this->controller()->subscribe($this->request(Browser::json()));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"subscribed":true}', $response->getContent());
        self::assertCount(1, $this->subscriptions->of('42'));

        $response = $this->controller()->unsubscribe($this->request('{"endpoint":"https://fcm.googleapis.com/fcm/send/abc:123"}'));
        self::assertSame(204, $response->getStatusCode());
        self::assertSame([], $this->subscriptions->of('42'));

        $response = $this->controller()->unsubscribe($this->request('{"endpoint":"https://fcm.googleapis.com/fcm/send/abc:123"}'));
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"not_found"}', $response->getContent());
    }

    public function testAVisitorOrAnotherSiteIsRefused(): void
    {
        $this->current->id = null;
        $response = $this->controller()->subscribe($this->request(Browser::json()));
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('{"error":"forbidden"}', $response->getContent());

        $this->current->id = '42';
        self::assertSame(403, $this->controller()->subscribe($this->request(Browser::json(), 'bad'))->getStatusCode());
        self::assertSame(403, $this->controller()->unsubscribe($this->request('{"endpoint":"x"}', 'bad'))->getStatusCode());
        self::assertSame([], $this->subscriptions->of('42'));
    }

    public function testWhatIsNoSubscriptionIsUnprocessable(): void
    {
        $response = $this->controller()->subscribe($this->request(Browser::json('https://push.example.com/a')));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_subscription', 'message' => 'The push service "push.example.com" is not one this site sends to.'], json_decode((string) $response->getContent(), true));
        self::assertSame(422, $this->controller()->unsubscribe($this->request('[]'))->getStatusCode());
    }

    public function testABodyLongerThanASubscriptionIsTooLarge(): void
    {
        $request = $this->request(Browser::json());
        $request->headers->set('Content-Length', (string) (PushSubscription::MAX_JSON + 1));

        $response = $this->controller()->subscribe($request);

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('{"error":"too_large"}', $response->getContent());

        $request->headers->set('Content-Length', (string) PushSubscription::MAX_JSON);
        self::assertSame(201, $this->controller()->subscribe($request)->getStatusCode());

        $request->headers->set('Content-Length', 'abc');
        self::assertSame(201, $this->controller()->subscribe($request)->getStatusCode());
    }

    private function controller(): PushSubscriptionController
    {
        return new PushSubscriptionController(new PushSubscriber($this->subscriptions), $this->current, new CentreRequest(new Tokens()));
    }

    private function request(string $body, string $token = 'good'): Request
    {
        return Request::create('/notifications/push/subscribe', 'POST', server: ['HTTP_X_PHIL_NOTIFICATIONS_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json'], content: $body);
    }
}
