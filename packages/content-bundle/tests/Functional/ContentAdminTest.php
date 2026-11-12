<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\EasyAdmin\ContentAdmin;
use Phil\ContentBundle\Infrastructure\Http\ContentAdminController;
use Phil\ContentBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The administration in a kernel: signed in, behind its role, every form with its CSRF token, every action through
 * the bundle's services.
 */
#[CoversClass(ContentAdminController::class)]
#[CoversClass(ContentAdmin::class)]
final class ContentAdminTest extends WebTestCase
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
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testThePagesAskTheRole(): void
    {
        $this->client->request('GET', '/admin/contents/');
        self::assertSame(401, $this->client->getResponse()->getStatusCode(), 'Nobody is signed in.');

        $this->signIn('reader');
        $this->client->request('GET', '/admin/contents/');
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheListShowsEachKindWithItsContentsAndTheirPlaces(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->contents()->publish($news, 'fr');
        $this->signIn('alice');

        $crawler = $this->client->request('GET', '/admin/contents/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertCount(1, $crawler->filter('#phil-content-admin-news'));
        self::assertCount(1, $crawler->filter('#phil-content-admin-static_page'));
        self::assertStringContainsString('ouverture', $crawler->filter('table')->text());
        self::assertSame('/admin/contents/'.$news->toString().'/fr', $crawler->filter('table a')->attr('href'));
    }

    public function testAContentIsCreatedThenEditedAndPublishedFromItsPage(): void
    {
        $this->signIn('alice');
        $crawler = $this->client->request('GET', '/admin/contents/new/news');
        $form = $crawler->filter('form')->form(['locale' => 'fr', 'slug' => 'ouverture']);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(303);
        $news = $this->contents()->list('news')[0];
        self::assertResponseRedirects('/admin/contents/'.$news->toString().'/fr');

        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Publish')->form(['content[excerptTitle]' => 'Ouverture', 'content[bodySource]' => 'Le site **ouvre**.']);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/contents/'.$news->toString().'/fr?done=published');
        self::assertSame('published', $this->contents()->status($news)[0]->place);
        self::assertSame('Ouverture', $this->contents()->live($news, 'fr')['excerptTitle']);
        $crawler = $this->client->followRedirect();
        self::assertSame('The content is published.', $crawler->filter('[role=status]')->text());

        $this->client->submit($crawler->selectButton('Take offline')->form());
        self::assertSame('unpublished', $this->contents()->status($news)[0]->place);
    }

    public function testAPublicationThatLosesContentIsRefusedUntilTheLossesAreAccepted(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'pertes', 'bodyFormat' => 'html', 'bodySource' => '<p><object>x</object></p>']);
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));

        self::assertStringContainsString('object', $crawler->filter('#phil-content-losses + ul')->text());

        $crawler = $this->client->submit($crawler->selectButton('Publish')->form());

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('would lose 1 element(s)', $crawler->filter('[role=alert]')->text());
        self::assertSame('unpublished', $this->contents()->status($news)[0]->place);

        $form = $crawler->selectButton('Publish')->form();
        $acceptLosses = $form['accept_losses'];
        self::assertInstanceOf(ChoiceFormField::class, $acceptLosses);
        $acceptLosses->tick();
        $this->client->submit($form);

        self::assertResponseRedirects($this->page($news).'?done=published');
        self::assertSame('published', $this->contents()->status($news)[0]->place);
    }

    public function testARefusedEditShowsTheReasonAndKeepsWhatWasTyped(): void
    {
        $this->contents()->create('news', 'fr', ['slug' => 'prise']);
        $news = $this->contents()->create('news', 'fr', ['slug' => 'libre']);
        $this->contents()->publish($this->contents()->list('news')[0], 'fr');
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));

        $crawler = $this->client->submit($crawler->selectButton('Save the draft')->form(['content[slug]' => 'prise', 'content[excerptTitle]' => 'Gardé']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $crawler->filter('[role=alert]'));
        self::assertSame('Gardé', $crawler->filter('#phil-content-excerptTitle')->attr('value'));
    }

    public function testTheAutosaveIsKeptForItsAuthorAloneAndRestoredFromThePage(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));
        $token = $crawler->filter('input[name=_token]')->attr('value');

        $this->client->request('POST', $this->page($news).'/autosave', ['_token' => $token, 'content' => ['excerptTitle' => 'Ouverture du site', 'seoNoIndex' => '1']]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(['excerptTitle' => 'Ouverture du site', 'seoNoIndex' => true], $this->copies()->autosaved($news, 'fr', 'alice')?->data());
        self::assertNull($this->copies()->autosaved($news, 'fr', 'bob'));

        $this->client->request('POST', $this->page($news).'/autosave', ['_token' => 'forged', 'content' => []]);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', $this->page($news).'/autosave', ['_token' => $token, 'content' => ['bodySource' => str_repeat('x', 300_000)]]);
        self::assertResponseStatusCodeSame(422);

        $crawler = $this->client->request('GET', $this->page($news));
        self::assertCount(1, $crawler->filter('#phil-content-autosave'));
        $this->client->submit($crawler->selectButton('Restore them')->form());

        self::assertResponseRedirects($this->page($news).'?done=autosave_applied');
        self::assertSame('Ouverture du site', $this->contents()->draft($news, 'fr')['excerptTitle']);
        self::assertNull($this->copies()->autosaved($news, 'fr', 'alice'));

        $this->signIn('bob');
        $crawler = $this->client->request('GET', $this->page($news));
        self::assertCount(0, $crawler->filter('#phil-content-autosave'), 'Another author sees no autosave.');
    }

    public function testANamedDraftIsSavedAppliedAndDeletedFromThePage(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));

        $this->client->submit($crawler->selectButton('Save as a named draft')->form(['name' => 'Court', 'content[excerptTitle]' => 'Court']));
        self::assertResponseRedirects($this->page($news).'?done=named_saved');
        self::assertSame('Ouverture', $this->contents()->draft($news, 'fr')['excerptTitle'], 'A named draft does not touch the draft.');

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Use it')->form());
        self::assertSame('Court', $this->contents()->draft($news, 'fr')['excerptTitle']);

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Delete')->form());
        self::assertSame([], $this->copies()->named($news, 'fr'));
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->signIn('alice');

        $this->client->request('POST', $this->page($news), ['_token' => 'forged', 'action' => 'publish']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('unpublished', $this->contents()->status($news)[0]->place);
    }

    public function testAnUnknownActionOrContentIsRefused(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->signIn('alice');
        $token = $this->client->request('GET', $this->page($news))->filter('input[name=_token]')->attr('value');

        $this->client->request('POST', $this->page($news), ['_token' => $token, 'action' => 'explode']);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/admin/contents/news:nope/fr');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/admin/contents/new/unknown');
        self::assertResponseStatusCodeSame(404);
    }

    public function testWithoutScheduleTheSchedulingFormIsNotShownAndRefused(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));

        self::assertCount(0, $crawler->filter('#phil-content-schedule'));

        $this->client->request('POST', $this->page($news), ['_token' => $crawler->filter('input[name=_token]')->attr('value'), 'action' => 'schedule', 'transition' => 'publish', 'at' => '2026-12-01T08:00']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheMenuLinksToTheList(): void
    {
        $items = ContentAdmin::menuItems();

        self::assertCount(1, $items);
        self::assertSame(ContentAdminController::LIST_ROUTE, $items[0]->getAsDto()->getRouteName());
    }

    /**
     * Signs in a user of the kernel's provider, so the firewall finds the same user on the next requests.
     */
    private function signIn(string $user): void
    {
        $provider = self::getContainer()->get('security.user.provider.concrete.editors');
        self::assertInstanceOf(UserProviderInterface::class, $provider);
        $this->client->loginUser($provider->loadUserByIdentifier($user));
    }

    private function page(ContentReference $content): string
    {
        return '/admin/contents/'.$content->toString().'/fr';
    }

    private function contents(): ContentManager
    {
        $service = self::getContainer()->get(ContentManager::class);
        self::assertInstanceOf(ContentManager::class, $service);
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        return $service;
    }

    private function copies(): WorkingCopies
    {
        $service = self::getContainer()->get(WorkingCopies::class);
        self::assertInstanceOf(WorkingCopies::class, $service);

        return $service;
    }
}
