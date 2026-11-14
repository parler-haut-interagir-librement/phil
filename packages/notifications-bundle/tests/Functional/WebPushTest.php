<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Infrastructure\WebPush\MinishlinkWebPushSender;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\NotificationsBundle\Tests\Functional\App\TestKernel;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Web Push in an application: the button carries what the controller needs, the browser's subscription is kept for
 * the recipient through the endpoints, with the centre's CSRF token, and forgotten again.
 */
#[CoversClass(PhilNotificationsBundle::class)]
final class WebPushTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create(), true, true)->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testABrowserIsSubscribedAndUnsubscribedFromThePage(): void
    {
        $crawler = $this->client->request('GET', '/page', server: ['HTTP_X_USER' => '42']);
        $button = $crawler->filter('[data-controller="phil--notifications-bundle--web-push"]');

        self::assertCount(1, $button);
        self::assertNotNull($button->attr('hidden'));
        self::assertSame('false', $crawler->filter('.phil-notifications-push__button')->attr('aria-pressed'));
        self::assertSame('Notifications in this browser', trim($crawler->filter('.phil-notifications-push__button')->text()));

        $config = json_decode((string) $button->attr('data-phil--notifications-bundle--web-push-config-value'), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame(['subscribe' => '/notifications/push/subscribe', 'unsubscribe' => '/notifications/push/unsubscribe'], $config['urls']);
        self::assertSame('BDpaYQAvNYnB8XOkYHuo8Lx4WGLWm5KzaN4ujfH356uc4dzZCkieV20Lj2mauyQZvOXvbl18TnC8wNls9UadiNs', $config['key']);
        self::assertSame('X-Phil-Notifications-Token', $config['header']);
        self::assertIsString($config['worker']);
        self::assertStringContainsString('push-worker', $config['worker']);
        self::assertIsArray($config['messages']);
        self::assertSame('Notifications in this browser are on.', $config['messages']['on']);
        self::assertIsString($config['token']);

        $server = ['HTTP_X_USER' => '42', 'HTTP_X_PHIL_NOTIFICATIONS_TOKEN' => $config['token'], 'HTTP_ORIGIN' => 'http://localhost', 'CONTENT_TYPE' => 'application/json'];
        $this->client->request('POST', '/notifications/push/subscribe', server: $server, content: Browser::json());
        self::assertResponseStatusCodeSame(201);

        $subscriptions = self::getContainer()->get(PushSubscriptions::class);
        self::assertInstanceOf(DbalPushSubscriptions::class, $subscriptions);
        self::assertCount(1, $subscriptions->of('42'));
        self::assertInstanceOf(MinishlinkWebPushSender::class, self::getContainer()->get(MinishlinkWebPushSender::class));

        $this->client->request('POST', '/notifications/push/unsubscribe', server: $server, content: '{"endpoint":"https://fcm.googleapis.com/fcm/send/abc:123"}');
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $subscriptions->of('42'));
    }

    public function testAVisitorSeesNoButtonAndCannotSubscribe(): void
    {
        $crawler = $this->client->request('GET', '/page');
        self::assertCount(0, $crawler->filter('.phil-notifications-push'));

        $this->client->request('POST', '/notifications/push/subscribe', server: ['CONTENT_TYPE' => 'application/json'], content: Browser::json());
        self::assertResponseStatusCodeSame(403);
    }
}
