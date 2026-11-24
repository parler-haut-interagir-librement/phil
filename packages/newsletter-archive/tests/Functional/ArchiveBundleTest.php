<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\NewsletterArchive\Application\Archive;
use Phil\NewsletterArchive\Application\IssueFeed;
use Phil\NewsletterArchive\Infrastructure\Console\ArchiveCommand;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchema;
use Phil\NewsletterArchive\Infrastructure\DependencyInjection\ArchiveServices;
use Phil\NewsletterArchive\Infrastructure\Http\ArchiveController;
use Phil\NewsletterArchive\Infrastructure\Routing\RouterIssueUrls;
use Phil\NewsletterArchive\PhilNewsletterArchiveBundle;
use Phil\NewsletterArchive\Tests\Fixture\Stage;
use Phil\NewsletterArchive\Tests\Functional\App\TestKernel;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The archive in an application: the list of the published issues, a page at a time, filtered by list and tag; an
 * issue at its slug, with its canonical address; a link that works until it is revoked; an issue unpublished that no
 * longer shows; no token nor address of a subscriber anywhere in a page, even from a template that writes them; and an
 * archive that erasing a subscriber leaves as it is.
 */
#[CoversClass(PhilNewsletterArchiveBundle::class)]
#[CoversClass(ArchiveServices::class)]
#[CoversClass(ArchiveController::class)]
#[CoversClass(RouterIssueUrls::class)]
#[CoversClass(ArchiveCommand::class)]
final class ArchiveBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(['page_size' => 5]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = $this->service(Connection::class, 'test.connection');

        foreach ([...ArchiveSchema::TABLES, ...NewsletterSchema::TABLES] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema(ArchiveSchema::configure(NewsletterSchema::configure($manager->introspectSchema())));

        $lists = $this->service(MailingListRepository::class);
        $lists->save(new MailingList('l1', Site::default(), 'news', 'News', false, 'default', new DateTimeImmutable()));
        $lists->save(new MailingList('l2', Site::default(), 'events', 'Events', false, 'default', new DateTimeImmutable()));
        $this->service(TemplateRepository::class)->save(new Template('branded', Site::default(), '{{ subject }}', Stage::TEMPLATE, new DateTimeImmutable()));
    }

    public function testTheArchiveListsThePublishedIssuesAPageAtATime(): void
    {
        for ($i = 1; $i <= 7; ++$i) {
            $this->sent('c'.$i, 'Issue '.$i, \sprintf('2026-10-%02d', $i), 0 === $i % 2 ? 'l2' : 'l1');
            $this->archive()->publish('c'.$i, 0 === $i % 3 ? ['games'] : []);
        }

        $this->sent('draft', 'A draft', null);
        $this->sent('c99', 'Never published', '2026-10-30');

        $crawler = $this->client->request('GET', '/newsletter/archive/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Newsletter archive');
        self::assertCount(5, $crawler->filter('ol.phil-newsletter-archive__issues > li'));
        self::assertSame('Issue 7', $crawler->filter('ol h2')->first()->text());
        self::assertSame('/newsletter/archive/issue-7', $crawler->filter('ol h2 a')->first()->attr('href'));
        self::assertSame('2026-10-07T00:00:00+00:00', $crawler->filter('ol time')->first()->attr('datetime'));
        self::assertSame('http://localhost/newsletter/archive/', $crawler->filter('link[rel=canonical]')->attr('href'));
        self::assertSame('Page 1 of 2', $crawler->filter('nav span')->text());
        self::assertSame('/newsletter/archive/?page=2', $crawler->filter('a[rel=next]')->attr('href'));
        self::assertCount(0, $crawler->filter('a[rel=prev]'));
        self::assertStringNotContainsString('Never published', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('A draft', (string) $this->client->getResponse()->getContent());

        $crawler = $this->client->request('GET', '/newsletter/archive/?page=2');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('ol.phil-newsletter-archive__issues > li'));
        self::assertSame('/newsletter/archive/', $crawler->filter('a[rel=prev]')->attr('href'));
        self::assertSame('http://localhost/newsletter/archive/?page=2', $crawler->filter('link[rel=canonical]')->attr('href'));

        $crawler = $this->client->request('GET', '/newsletter/archive/?list=events');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('ol.phil-newsletter-archive__issues > li'));
        self::assertSame('page', $crawler->filter('a[aria-current=page]')->first()->attr('aria-current'));
        self::assertSame('Events', $crawler->filter('a[aria-current=page]')->first()->text());

        $crawler = $this->client->request('GET', '/newsletter/archive/?list=events&tag=games');
        self::assertResponseIsSuccessful();
        self::assertSame(['Issue 6'], $crawler->filter('ol h2')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame('/newsletter/archive/?tag=games', $crawler->filter('nav p')->first()->filter('a')->first()->attr('href'));

        $this->client->request('GET', '/newsletter/archive/?list=other');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main > p', 'No issue is published yet.');

        foreach (['?page=3', '?page=0', '?page=abc', '?page=99999', '?list=Bad%20List', '?tag=Not%20A%20Tag', '?list=events&page=2'] as $query) {
            $this->client->request('GET', '/newsletter/archive/'.$query);
            self::assertResponseStatusCodeSame(404, $query);
        }
    }

    public function testAnIssueAtItsSlugAndAnIssueUnpublished(): void
    {
        $this->sent('c1', 'Hello world', '2026-11-01');
        $this->archive()->publish('c1', ['games']);

        $crawler = $this->client->request('GET', '/newsletter/archive/hello-world');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Hello world');
        self::assertSame('http://localhost/newsletter/archive/hello-world', $crawler->filter('link[rel=canonical]')->attr('href'));
        self::assertSame('Hello world', $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertSame('This month in short', $crawler->filter('meta[name=description]')->attr('content'));
        self::assertSame('/newsletter/archive/?tag=games', $crawler->filter('.phil-newsletter-archive__tags a')->attr('href'));
        self::assertSame('readers', $crawler->filter('.phil-newsletter-archive__content strong')->text());
        self::assertCount(0, $crawler->filter('meta[name=robots]'));
        self::assertNull($this->client->getResponse()->headers->get('Referrer-Policy'));

        $this->archive()->unpublish('c1');

        $this->client->request('GET', '/newsletter/archive/hello-world');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/newsletter/archive/');
        self::assertStringNotContainsString('Hello world', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/newsletter/archive/'.str_repeat('a', 256));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/newsletter/archive/none');
        self::assertResponseStatusCodeSame(404);
    }

    public function testALinkWorksUntilItIsRevoked(): void
    {
        $this->sent('c1', 'Shared issue', '2026-11-01');
        $token = $this->archive()->share('c1');

        $crawler = $this->client->request('GET', '/newsletter/archive/shared/'.$token);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Shared issue');
        $response = $this->client->getResponse();
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name=robots]')->attr('content'));
        self::assertCount(0, $crawler->filter('link[rel=canonical]'));
        self::assertCount(0, $crawler->filter('footer'));

        $this->archive()->publish('c1');
        $crawler = $this->client->request('GET', '/newsletter/archive/shared/'.$token);
        self::assertSame('http://localhost/newsletter/archive/shared-issue', $crawler->filter('link[rel=canonical]')->attr('href'));

        $this->archive()->revokeLink('c1');
        $this->client->request('GET', '/newsletter/archive/shared/'.$token);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/newsletter/archive/shared/'.str_repeat('A', 43));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/newsletter/archive/shared/short');
        self::assertResponseStatusCodeSame(404);
    }

    public function testALinkOfAnotherSiteIsNotFound(): void
    {
        $this->service(CampaignRepository::class)->save(Campaign::restore(
            new Campaign('c1', Site::of('other'), 'l1', 'Elsewhere', 'Body'),
            CampaignStatus::Sent,
            null,
            new DateTimeImmutable('2026-11-01'),
        ));
        $token = $this->archive()->share('c1');

        $this->client->request('GET', '/newsletter/archive/shared/'.$token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testNoSubscriberTokenNorAddressIsInThePagesAndErasingASubscriberChangesNothing(): void
    {
        $subscriber = new Subscriber('s1', Site::default(), 'ada@example.com', 'ext-ada', SubscriberStatus::Active, new DateTimeImmutable());
        $this->service(SubscriberRepository::class)->save($subscriber);
        $links = $this->service(SignedLinks::class);
        $tokens = [
            $links->sign(LinkPurpose::Unsubscribe, 's1', 'l1', 31_536_000),
            $links->sign(LinkPurpose::Preferences, 's1', '', 31_536_000),
        ];
        $this->sent('c1', 'Branded issue', '2026-11-01', template: 'branded');
        $this->archive()->publish('c1');
        $token = $this->archive()->share('c1');
        $before = $this->connection()->fetchOne('SELECT html FROM '.ArchiveSchema::ISSUES);

        foreach (['/newsletter/archive/', '/newsletter/archive/branded-issue', '/newsletter/archive/shared/'.$token] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseIsSuccessful();
            $html = (string) $this->client->getResponse()->getContent();

            foreach ([...$tokens, 'subscriber.invalid', '/newsletter/unsubscribe/', '/newsletter/preferences/', '/newsletter/c/', 'ada@example.com', 'ext-ada', 'tracker.example', 'Unsubscribe', 'Preferences'] as $secret) {
                self::assertStringNotContainsString($secret, $html, $page);
            }
        }

        self::assertIsString($before);
        self::assertStringContainsString('Sent to News on default.', $before);

        $this->service(Subscriptions::class)->forget($subscriber);

        self::assertNull($this->service(SubscriberRepository::class)->get('s1'));
        self::assertSame($before, $this->connection()->fetchOne('SELECT html FROM '.ArchiveSchema::ISSUES));
        $this->client->request('GET', '/newsletter/archive/branded-issue');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Sent to News on default.', (string) $this->client->getResponse()->getContent());
    }

    public function testTheCommand(): void
    {
        $this->sent('c1', 'Hello world', '2026-11-01');
        $command = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find(ArchiveCommand::NAME));

        self::assertSame(0, $command->execute(['action' => 'publish', 'campaign' => 'c1', '--tag' => ['games', 'news']]));
        self::assertStringContainsString('Published at https://example.com/newsletter/archive/hello-world', $command->getDisplay());
        self::assertSame(['games', 'news'], $this->archive()->publish('c1')->tagValues());

        self::assertSame(0, $command->execute(['action' => 'retag', 'campaign' => 'c1', '--tag' => ['events']]));
        self::assertStringContainsString('Retagged.', $command->getDisplay());

        self::assertSame(0, $command->execute(['action' => 'share', 'campaign' => 'c1']));
        self::assertSame(1, preg_match('#https://example\.com/newsletter/archive/shared/([A-Za-z0-9_-]{43})#', $command->getDisplay(), $link));
        $this->client->request('GET', '/newsletter/archive/shared/'.$link[1]);
        self::assertResponseIsSuccessful();

        self::assertSame(0, $command->execute(['action' => 'revoke', 'campaign' => 'c1']));
        self::assertStringContainsString('The link is revoked.', $command->getDisplay());
        $this->client->request('GET', '/newsletter/archive/shared/'.$link[1]);
        self::assertResponseStatusCodeSame(404);

        self::assertSame(0, $command->execute(['action' => 'unpublish', 'campaign' => 'c1']));
        self::assertStringContainsString('Unpublished', $command->getDisplay());
        $this->client->request('GET', '/newsletter/archive/hello-world');
        self::assertResponseStatusCodeSame(404);

        self::assertSame(1, $command->execute(['action' => 'publish', 'campaign' => 'none']));
        self::assertStringContainsString('No campaign "none".', $command->getDisplay());
        self::assertSame(1, $command->execute(['action' => 'publish', 'campaign' => 'c1', '--tag' => ['Bad Tag']]));
        self::assertSame(2, $command->execute(['action' => 'delete', 'campaign' => 'c1']));
        self::assertStringContainsString('"delete" is not an action: publish, unpublish, share, revoke, retag.', $command->getDisplay());
    }

    public function testTheFeedReadsThePublishedIssues(): void
    {
        $this->sent('c1', 'Hello world', '2026-11-01');
        $this->archive()->publish('c1');

        $items = $this->service(IssueFeed::class)->latest('default');
        self::assertSame('https://example.com/newsletter/archive/hello-world', $items[0]->url);
    }

    private function sent(string $id, string $subject, ?string $sentAt, string $listId = 'l1', ?string $template = null): void
    {
        $campaign = new Campaign($id, Site::default(), $listId, $subject, "Hello **readers**.\n\n[Our site](https://example.com/)", 'This month in short', null, $template, null, ClickTracking::Aggregate);

        if (null !== $sentAt) {
            $campaign = Campaign::restore($campaign, CampaignStatus::Sent, null, new DateTimeImmutable($sentAt.' UTC'));
        }

        $this->service(CampaignRepository::class)->save($campaign);
    }

    private function archive(): Archive
    {
        return $this->service(Archive::class);
    }

    private function connection(): Connection
    {
        return $this->service(Connection::class, 'test.connection');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
