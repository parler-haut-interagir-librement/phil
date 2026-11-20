<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\BackOffice;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\ModerationBundle\Infrastructure\EasyAdmin\ModerationAdmin;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

#[CoversClass(AdminController::class)]
#[CoversClass(ModerationAdmin::class)]
final class BackOfficeTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return BackOfficeKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $manager = $connection->createSchemaManager();

        foreach (ModerationTables::tables() as $table) {
            $name = $table->getObjectName()->toString();

            if ($manager->tablesExist([$name])) {
                $manager->dropTable($name);
            }

            $manager->createTable($table);
        }

        $connection->insert(ModerationTables::REPORTS, [
            'id' => str_repeat('a', 32), 'target_type' => 'forum.post', 'target_id' => '1', 'source' => 'member', 'reason' => 'spam',
            'reporter' => 'm-1', 'reported_at' => '2026-11-13 10:00:00',
        ]);
    }

    public function testThePagesAreGraftedOnTheDashboard(): void
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/admin/moderation', $router->generate('admin_moderation_queue'));
        self::assertSame('/admin/moderation/entry', $router->generate('admin_moderation_entry'));
        self::assertSame(['POST'], $router->getRouteCollection()->get('admin_moderation_decide')?->getMethods());
        self::assertNull($router->getRouteCollection()->get('admin_moderation_incidents'), "the incident mode is phil/incident-mode-bundle's page");
        self::assertNull($router->getRouteCollection()->get(AdminController::QUEUE_ROUTE), 'the application imports the routes of the bundle only if it wants them');
    }

    public function testAModeratorDecidesInTheBackOffice(): void
    {
        $provider = self::getContainer()->get('security.user.provider.concrete.people');
        self::assertInstanceOf(UserProviderInterface::class, $provider);
        $this->client->loginUser($provider->loadUserByIdentifier('mod-1'));
        $queue = $this->client->request('GET', '/admin/moderation');

        self::assertResponseIsSuccessful();
        self::assertSame('Moderation queue', trim($queue->filter('.content-header-title h1, h1.title')->first()->text()));
        self::assertCount(1, $queue->filter('.ea-sidebar-group-items a[href="http://localhost/admin/moderation"]'), 'the menu of the dashboard links to the queue');
        self::assertCount(0, $queue->filter('a[href*="incidents"]'), 'without phil/incident-mode-bundle, no incident mode is offered');

        $entry = $this->client->click($queue->filter('a:contains("Cheap watches")')->link());
        self::assertResponseIsSuccessful();
        $this->client->submit($entry->filter('form[action="/admin/moderation/entry"]')->form(['action' => 'remove', 'reason' => 'Spam', 'ground' => 'spam', 'explanation' => 'An advertisement.']));

        self::assertResponseRedirects('/admin/moderation');
    }
}
