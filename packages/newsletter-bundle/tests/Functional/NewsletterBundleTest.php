<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices;
use Phil\NewsletterBundle\Infrastructure\Http\ClickController;
use Phil\NewsletterBundle\Infrastructure\Http\PreviewController;
use Phil\NewsletterBundle\Infrastructure\Http\SubscriptionController;
use Phil\NewsletterBundle\PhilNewsletterBundle;
use Phil\NewsletterBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Email;

/**
 * The bundle in an application: the subscription form and its CSRF token, the double opt-in through Messenger and the
 * mailer, the confirmation and the unsubscription pages, the mail client's one-click unsubscription, the preference
 * center, a campaign sent and its tracked link followed, and the preview behind its role.
 */
#[CoversClass(PhilNewsletterBundle::class)]
#[CoversClass(NewsletterServices::class)]
#[CoversClass(SubscriptionController::class)]
#[CoversClass(ClickController::class)]
#[CoversClass(PreviewController::class)]
final class NewsletterBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('test.connection');
        self::assertInstanceOf(Connection::class, $connection);

        foreach (NewsletterSchema::TABLES as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema(NewsletterSchema::configure($manager->introspectSchema()));
        $this->service(MailingListRepository::class)->save(new MailingList('l1', Site::default(), 'news', 'News', true, 'default', new DateTimeImmutable()));
        $this->service(MailingListRepository::class)->save(new MailingList('l2', Site::default(), 'events', 'Events', false, 'default', new DateTimeImmutable()));
    }

    public function testADoubleOptInFromTheFormToTheConfirmation(): void
    {
        $crawler = $this->client->request('GET', '/newsletter/subscribe/news');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('noindex', $this->client->getResponse()->headers->get('X-Robots-Tag'));
        $form = $crawler->selectButton('Subscribe')->form(['email' => 'ada@example.com']);

        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role=status]', 'Thank you.');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Confirm your subscription to News', $email->getSubject());
        self::assertSame(1, preg_match('#https://example\.com(/newsletter/confirm/[A-Za-z0-9_.-]+)#', (string) $email->getHtmlBody(), $link));

        $crawler = $this->client->request('GET', $link[1]);
        self::assertResponseIsSuccessful();
        self::assertSame(SubscriberStatus::Pending, $this->subscriber('ada@example.com')->status);
        $this->client->submit($crawler->selectButton('Confirm')->form());
        self::assertSelectorTextContains('h1', 'Your subscription is confirmed.');
        self::assertSame(SubscriberStatus::Active, $this->subscriber('ada@example.com')->status);

        $this->client->request('GET', '/newsletter/confirm/forged.AAAAAAAAAAAAAAAAAAAAAA');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/newsletter/subscribe/none');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheFormRefusesABadAddressAnExpiredTokenAndRobots(): void
    {
        $crawler = $this->client->request('GET', '/newsletter/subscribe/news');
        $this->client->submit($crawler->selectButton('Subscribe')->form(['email' => 'not an address']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'This address cannot receive our messages.');

        $this->client->request('POST', '/newsletter/subscribe/news', ['email' => 'ada@example.com', '_token' => 'wrong']);
        self::assertResponseStatusCodeSame(400);

        $crawler = $this->client->request('GET', '/newsletter/subscribe/news');
        $this->client->submit($crawler->selectButton('Subscribe')->form(['email' => 'robot@example.com', 'website' => 'https://spam.example']));
        self::assertResponseIsSuccessful();
        self::assertNull($this->service(SubscriberRepository::class)->byEmail(Site::default(), 'robot@example.com'));
        self::assertEmailCount(0);
    }

    public function testACampaignIsSentAndItsLinkFollowedThenTheMailClientUnsubscribes(): void
    {
        $crawler = $this->client->request('GET', '/newsletter/subscribe/events');
        $this->client->submit($crawler->selectButton('Subscribe')->form(['email' => 'ada@example.com']));
        $campaign = new Campaign('c1', Site::default(), 'l2', 'Hello', '[Read](https://example.org/read)');
        $this->service(CampaignRepository::class)->save($campaign);

        $this->service(Campaigns::class)->sendNow($campaign);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringStartsWith('bounces+', (string) $email->getReturnPath()?->getAddress());
        self::assertSame(1, preg_match('#https://example\.com(/newsletter/c/[A-Za-z0-9_.-]+)#', (string) $email->getHtmlBody(), $click));

        $this->client->request('GET', $click[1]);
        self::assertResponseRedirects('https://example.org/read', 302);
        $this->client->request('GET', '/newsletter/c/forged.AAAAAAAAAAAAAAAAAAAAAA');
        self::assertResponseStatusCodeSame(404);

        self::assertSame(1, preg_match('#<https://example\.com(/newsletter/unsubscribe/[^>]+)>#', (string) $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString(), $unsubscribe));
        $this->client->request('GET', $unsubscribe[1]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name=reason]');
        $this->client->request('POST', $unsubscribe[1], ['List-Unsubscribe' => 'One-Click']);
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertSame(SubscriberStatus::Unsubscribed, $this->subscriber('ada@example.com')->status);

        $crawler = $this->client->request('GET', $unsubscribe[1]);
        $this->client->submit($crawler->selectButton('Unsubscribe')->form(['reason' => 'too_frequent']));
        self::assertSelectorTextContains('h1', 'You are unsubscribed.');
    }

    public function testThePreferenceCenter(): void
    {
        $crawler = $this->client->request('GET', '/newsletter/subscribe/events');
        $this->client->submit($crawler->selectButton('Subscribe')->form(['email' => 'ada@example.com']));
        $campaign = new Campaign('c1', Site::default(), 'l2', 'Hello', 'Hi');
        $this->service(CampaignRepository::class)->save($campaign);
        $this->service(Campaigns::class)->sendNow($campaign);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(1, preg_match('#https://example\.com(/newsletter/preferences/[A-Za-z0-9_.-]+)#', (string) $email->getHtmlBody(), $link));
        $id = $this->subscriber('ada@example.com')->id;

        $crawler = $this->client->request('GET', $link[1]);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save')->form();
        $lists = $form['lists'];
        self::assertIsArray($lists);
        self::assertInstanceOf(ChoiceFormField::class, $lists[0]);
        self::assertInstanceOf(ChoiceFormField::class, $lists[1]);
        $lists[0]->untick();
        $lists[1]->tick();
        $this->client->submit($form);
        self::assertSelectorTextContains('[role=status]', 'Your preferences are saved.');
        $subscribers = $this->service(SubscriberRepository::class);
        self::assertSame(MembershipStatus::Unsubscribed, $subscribers->membership($id, 'l2')?->status);
        self::assertSame(MembershipStatus::Active, $subscribers->membership($id, 'l1')?->status);

        $crawler = $this->client->request('GET', $link[1]);
        $this->client->submit($crawler->selectButton('Pause')->form(['weeks' => '12']));
        self::assertSame(SubscriberStatus::Paused, $this->subscriber('ada@example.com')->status);
        $crawler = $this->client->request('GET', $link[1]);
        $this->client->submit($crawler->selectButton('Receive them again now')->form());
        self::assertSame(SubscriberStatus::Active, $this->subscriber('ada@example.com')->status);
        $crawler = $this->client->request('GET', $link[1]);
        $this->client->submit($crawler->selectButton('Unsubscribe from everything')->form(['reason' => 'other']));
        self::assertSame(SubscriberStatus::Unsubscribed, $this->subscriber('ada@example.com')->status);

        $this->client->request('POST', $link[1], ['action' => 'resume', '_token' => 'wrong']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/newsletter/preferences/forged.AAAAAAAAAAAAAAAAAAAAAA');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePreviewAnswersItsRoleOnly(): void
    {
        $this->client->request('POST', '/newsletter/preview', ['markdown' => '# Title'], server: ['PHP_AUTH_USER' => 'reader', 'PHP_AUTH_PW' => 'reader']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/newsletter/preview', ['markdown' => '# Title', 'subject' => 'S'], server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Title</h1>', (string) $this->client->getResponse()->getContent());
        self::assertStringStartsWith('sandbox;', (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));

        $this->client->request('POST', '/newsletter/preview', ['markdown' => str_repeat('x', 262_145)], server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        self::assertResponseStatusCodeSame(413);
        $this->client->request('POST', '/newsletter/preview', ['markdown' => 'x', 'template' => 'missing'], server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        self::assertResponseIsSuccessful();
    }

    private function subscriber(string $email): Subscriber
    {
        $subscriber = $this->service(SubscriberRepository::class)->byEmail(Site::default(), $email);
        self::assertInstanceOf(Subscriber::class, $subscriber);

        return $subscriber;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
