<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPreferences;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Infrastructure\Http\DigestLinks;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerDigestSender;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\NotificationsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Environment;

/**
 * The bundle in an application: a notification sent through the entry point lands in the centre, the bell and the list
 * show it, and the centre's forms mark it read with the CSRF token; the signed link of a digest stops it in one click.
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

    public function testTheSignedLinkOfADigestStopsItInOneClick(): void
    {
        $preferences = self::getContainer()->get(DbalPreferences::class);
        self::assertInstanceOf(DbalPreferences::class, $preferences);
        $preferences->save('42', new RecipientSettings(DigestFrequency::Weekly, locale: 'fr'));
        $links = self::getContainer()->get(DigestLinks::class);
        self::assertInstanceOf(DigestLinks::class, $links);
        $url = (string) $links->unsubscribe('42');
        self::assertStringStartsWith('http://localhost/notifications/digest/unsubscribe/42?_expiration=', $url);
        self::assertSame('http://localhost/settings', $links->preferences());

        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('p', 'Stop the digest of your notifications?');
        self::assertSame($url, $crawler->filter('form[method="post"]')->attr('action'));
        self::assertSame(DigestFrequency::Weekly, $preferences->settingsFor('42')->digest, 'a GET changes nothing');

        $this->client->request('POST', $url, ['List-Unsubscribe' => 'One-Click']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('p', 'Done: the digest stops.');
        self::assertSame(DigestFrequency::Never, $preferences->settingsFor('42')->digest);
        self::assertSame('fr', $preferences->settingsFor('42')->locale);

        $this->client->request('POST', str_replace('/42?', '/43?', $url));
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('p', 'This link is not valid or has expired.');
    }

    public function testTheTemplatesOfTheDigestWriteItsLinks(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $context = [
            'frequency' => 'daily',
            'notifications' => [
                ['id' => 'n1', 'type' => 'comment.replied', 'title' => 'Ada replied & more', 'body' => 'Two replies', 'url' => 'https://example.test/c/9', 'count' => 2],
                ['id' => 'n2', 'type' => 'comment.replied', 'title' => 'Bob replied', 'body' => null, 'url' => null, 'count' => 1],
            ],
            'preferences_url' => 'https://example.test/settings',
            'unsubscribe_url' => 'https://example.test/notifications/digest/unsubscribe/42?_expiration=1&_hash=h',
        ];

        self::assertSame(
            "What happened today\n\n- Ada replied & more\n  Two replies\n  https://example.test/c/9\n\n- Bob replied\n\nYou get this digest because you chose it in your notification preferences.\nYour notification preferences: https://example.test/settings\nStop this digest: https://example.test/notifications/digest/unsubscribe/42?_expiration=1&_hash=h",
            trim($twig->render(MailerDigestSender::TEXT_TEMPLATE, $context)),
        );
        $html = $twig->render(MailerDigestSender::TEMPLATE, $context);
        self::assertStringContainsString('<a href="https://example.test/settings">Your notification preferences</a>', $html);
        self::assertStringContainsString('<a href="https://example.test/notifications/digest/unsubscribe/42?_expiration=1&amp;_hash=h">Stop this digest</a>', $html);
        self::assertStringContainsString('Ada replied &amp; more', $html);

        $plain = trim($twig->render(MailerDigestSender::TEXT_TEMPLATE, ['preferences_url' => null, 'unsubscribe_url' => null] + $context));
        self::assertStringEndsWith('You get this digest because you chose it in your notification preferences.', $plain);
        $bare = $twig->render(MailerDigestSender::TEMPLATE, ['preferences_url' => null, 'unsubscribe_url' => null] + $context);
        self::assertStringNotContainsString('settings', $bare);
        self::assertStringNotContainsString('·', $bare);
        self::assertStringNotContainsString('·', $twig->render(MailerDigestSender::TEMPLATE, ['unsubscribe_url' => null] + $context));
    }

    public function testAChangeFromAnotherSiteIsRefused(): void
    {
        $this->client->request('POST', '/notifications/read-all', ['_token' => 'csrf-token'], server: ['HTTP_X_USER' => '42', 'HTTP_ORIGIN' => 'https://evil.test', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseStatusCodeSame(403);
    }
}
