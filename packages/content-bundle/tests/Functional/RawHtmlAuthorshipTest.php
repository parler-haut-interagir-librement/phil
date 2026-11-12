<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Editing\AuthorRawHtml;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Markup\RawHtmlAuthorPass;
use Phil\ContentBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The right to write raw HTML in a kernel, through the administration: carol holds `raw_html_role`, alice does not.
 * The right is checked on the author of each revision, stored with the body, never on whoever publishes it.
 */
#[CoversClass(RawHtmlAuthorPass::class)]
#[CoversClass(AuthorRawHtml::class)]
final class RawHtmlAuthorshipTest extends WebTestCase
{
    private const string RAW = "Le site **ouvre**.\n\n<div><p>Bonjour</p><img src=\"https://example.org/x.png\" alt=\"\"></div>";

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
        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testTheRawHtmlOfAnAuthorWithoutTheRightIsPublishedAsTextByAnEditorWhoHoldsIt(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));
        $this->client->submit($crawler->selectButton('Save the draft')->form(['content[bodySource]' => self::RAW]));
        self::assertFalse($this->contents()->draft($news, 'fr')['bodyRawHtml']);

        $this->signIn('carol');
        $crawler = $this->client->request('GET', $this->page($news));
        $this->client->submit($crawler->selectButton('Publish')->form());

        $live = $this->contents()->live($news, 'fr');
        self::assertStringNotContainsString('"type":"raw"', self::text($live['bodySource']));
        self::assertStringNotContainsString('<img', self::text($live['bodyHtml']));
        self::assertStringContainsString('&lt;div&gt;', self::text($live['bodyHtml']));
    }

    public function testTheRawHtmlOfAnAuthorWhoHoldsTheRightIsPublishedByAnyEditor(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->signIn('carol');
        $crawler = $this->client->request('GET', $this->page($news));
        $this->client->submit($crawler->selectButton('Save the draft')->form(['content[bodySource]' => self::RAW]));
        self::assertTrue($this->contents()->draft($news, 'fr')['bodyRawHtml']);

        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));
        $this->client->submit($crawler->selectButton('Publish')->form());

        $live = $this->contents()->live($news, 'fr');
        self::assertStringContainsString('"type":"raw"', self::text($live['bodySource']));
        self::assertStringContainsString('<img', self::text($live['bodyHtml']));
    }

    /**
     * A named draft written by alice stays text when carol applies it.
     */
    public function testANamedDraftKeepsTheRightOfItsAuthor(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->signIn('alice');
        $crawler = $this->client->request('GET', $this->page($news));
        $this->client->submit($crawler->selectButton('Save as a named draft')->form(['name' => 'Court', 'content[bodySource]' => self::RAW]));

        $this->signIn('carol');
        $crawler = $this->client->request('GET', $this->page($news));
        $this->client->submit($crawler->selectButton('Use it')->form());
        self::assertSame(self::RAW, $this->contents()->draft($news, 'fr')['bodySource']);
        self::assertFalse($this->contents()->draft($news, 'fr')['bodyRawHtml']);
    }

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
        $this->entityManager()->clear();

        return $service;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private static function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
