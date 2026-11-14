<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Infrastructure\Http\CentreController;
use Phil\NotificationsBundle\Infrastructure\Twig\BellComponent;
use Phil\NotificationsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The centre under Symfony UX Turbo: the bell kept across visits as a permanent element, a form of the centre that
 * Turbo submits answered with the redirect Turbo follows, and the unread count still JSON.
 */
#[CoversClass(BellComponent::class)]
#[CoversClass(CentreController::class)]
final class TurboTest extends WebTestCase
{
    private const string TURBO_ACCEPT = 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml';

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

        $notifications = self::getContainer()->get(Notifications::class);
        self::assertInstanceOf(Notifications::class, $notifications);
        $notifications->send('comment.replied', '42', new SubjectReference('comment', '9'), [], '17');
    }

    public function testTheBellIsAPermanentElementTurboKeepsAcrossVisits(): void
    {
        $this->client->request('GET', '/turbo-page', server: ['HTTP_X_USER' => '42']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#phil-notifications-bell[data-turbo-permanent][data-controller="phil--notifications-bundle--notifications"]');
        self::assertSelectorTextSame('#phil-notifications-bell .phil-notifications-bell__count', '1');
        self::assertSelectorExists('turbo-frame#notifications .phil-notifications-item--unread');
    }

    public function testAFormTurboSubmitsIsAnsweredWithTheRedirectItFollows(): void
    {
        $crawler = $this->client->request('GET', '/turbo-page', server: ['HTTP_X_USER' => '42']);
        $form = $crawler->filter('main form[action$="/read"]')->form();

        $this->client->submit($form, [], ['HTTP_X_USER' => '42', 'HTTP_ACCEPT' => self::TURBO_ACCEPT, 'HTTP_REFERER' => 'http://localhost/turbo-page', 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertResponseRedirects('http://localhost/turbo-page', 303);
    }

    public function testTheUnreadCountStaysJsonForATurboVisit(): void
    {
        $this->client->request('GET', '/notifications/unread', server: ['HTTP_X_USER' => '42', 'HTTP_ACCEPT' => self::TURBO_ACCEPT]);

        self::assertResponseIsSuccessful();
        self::assertSame('{"unread":1}', $this->client->getResponse()->getContent());
    }
}
