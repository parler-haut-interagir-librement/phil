<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\NotificationsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The bundle in an application: a notification sent through the entry point lands in the centre, the bell and the list
 * show it, and the centre's forms mark it read with the CSRF token.
 */
#[CoversClass(PhilNotificationsBundle::class)]
final class NotificationsBundleTest extends WebTestCase
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

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testANotificationShowsInTheCentre(): void
    {
        $notifications = self::getContainer()->get(Notifications::class);
        self::assertInstanceOf(Notifications::class, $notifications);
        $notifications->send('comment.replied', '42', new SubjectReference('comment', '9'), ['event' => 7], '17');
        $notifications->send('comment.replied', '42', new SubjectReference('comment', '9'), ['event' => 7], '17');

        $crawler = $this->client->request('GET', '/page', server: ['HTTP_X_USER' => '42']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.phil-notifications-bell__count', '1');
        self::assertSelectorExists('[data-controller="phil--notifications-bundle--notifications"]');
        self::assertCount(2, $crawler->filter('.phil-notifications-item--unread'), 'the bell and the list each show it, collapsed');
        self::assertSelectorTextContains('.phil-notifications-bell__count + .phil-notifications-visually-hidden', 'One unread notification');

        $form = $crawler->filter('main form[action$="/read"]')->form();
        $this->client->submit($form, [], ['HTTP_X_USER' => '42', 'HTTP_REFERER' => 'http://localhost/page', 'HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('http://localhost/page', 303);

        $this->client->request('GET', '/notifications/unread', server: ['HTTP_X_USER' => '42']);
        self::assertSame('{"unread":0}', $this->client->getResponse()->getContent());
    }

    public function testAVisitorSeesNoBell(): void
    {
        $this->client->request('GET', '/page');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.phil-notifications-bell');
        self::assertSelectorTextContains('.phil-notifications-list__empty', 'No notification yet.');
    }

    public function testAChangeFromAnotherSiteIsRefused(): void
    {
        $this->client->request('POST', '/notifications/read-all', ['_token' => 'csrf-token'], server: ['HTTP_X_USER' => '42', 'HTTP_ORIGIN' => 'https://evil.test', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseStatusCodeSame(403);
    }
}
