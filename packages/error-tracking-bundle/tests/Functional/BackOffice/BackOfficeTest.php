<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional\BackOffice;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The administration pages grafted on an EasyAdmin dashboard, followed as an operator reads them: the menu's link,
 * the list in the dashboard's layout, a group, and the form that resolves it.
 */
#[CoversNothing]
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
        $schemaManager = $connection->createSchemaManager();

        foreach ([ErrorTables::EVENT, ErrorTables::GROUP] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }

        foreach (ErrorTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testThePagesAreGraftedOnTheDashboard(): void
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertSame('/admin/errors', $router->generate('admin_errors_groups'));
        self::assertSame('/admin/errors/'.str_repeat('a', 64), $router->generate('admin_errors_group', ['fingerprint' => str_repeat('a', 64)]));
        self::assertSame(['POST'], $router->getRouteCollection()->get('admin_errors_group_status')?->getMethods());
        self::assertNull($router->getRouteCollection()->get('phil_error_tracking_groups'), 'the application imports the routes of the bundle only if it wants them');
    }

    public function testAnOperatorReadsAndResolvesAGroupInTheBackOffice(): void
    {
        $tracker = self::getContainer()->get(ErrorTracker::class);
        self::assertInstanceOf(ErrorTracker::class, $tracker);
        $fingerprint = $tracker->captureThrowable(new RuntimeException('The export failed'))?->fingerprint;
        self::assertIsString($fingerprint);

        $this->client->loginUser($this->account('operator'));
        $list = $this->client->request('GET', '/admin/errors');
        self::assertResponseIsSuccessful();
        self::assertSame('Errors', trim($list->filter('.content-header-title h1, h1.title')->first()->text()));
        self::assertCount(1, $list->filter('.ea-sidebar-group-items a[href="http://localhost/admin/errors"]'), 'the menu of the dashboard links to the list');
        self::assertCount(1, $list->filter('a[href="/admin/errors/'.$fingerprint.'"]'));

        $group = $this->client->click($list->filter('a[href="/admin/errors/'.$fingerprint.'"]')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $group->filter('a[href="/admin/errors"]:contains("All errors")'));

        $this->client->submit($group->filter('form[action="/admin/errors/'.$fingerprint.'/resolve"]')->form());
        self::assertResponseRedirects('/admin/errors/'.$fingerprint, 303);

        $reader = self::getContainer()->get(ErrorGroupReader::class);
        self::assertInstanceOf(ErrorGroupReader::class, $reader);
        self::assertSame(GroupStatus::Resolved, $reader->groups(null, 1)[0]->status);
    }

    public function testAMemberIsDenied(): void
    {
        $this->client->loginUser($this->account('member'));
        $this->client->request('GET', '/admin/errors');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * An account of the provider in memory, as the firewall reloads it from the session.
     */
    private function account(string $identifier): UserInterface
    {
        $provider = self::getContainer()->get('security.user.provider.concrete.people');
        self::assertInstanceOf(UserProviderInterface::class, $provider);

        return $provider->loadUserByIdentifier($identifier);
    }
}
