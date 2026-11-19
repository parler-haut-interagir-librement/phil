<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ShortLinkBundle\Application\ShortLinkDraft;
use Phil\ShortLinkBundle\Application\ShortLinks;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Infrastructure\Form\ShortLinkType;
use Phil\ShortLinkBundle\Infrastructure\Http\ShortLinkAdminController;
use Phil\ShortLinkBundle\PhilShortLinkBundle;
use Phil\ShortLinkBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The bundle in an application: the short link domains answered before the routing, and the administration behind
 * the role, its form, its buttons with their CSRF token and the QR codes.
 */
#[CoversClass(PhilShortLinkBundle::class)]
#[CoversClass(ShortLinkAdminController::class)]
#[CoversClass(ShortLinkType::class)]
final class ShortLinkBundleTest extends WebTestCase
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
        $entityManager = self::getContainer()->get('test.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $cache = self::getContainer()->get('test.cache');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
    }

    public function testAShortLinkDomainAnswersBeforeTheRoutingAndTheSiteGoesOn(): void
    {
        $link = $this->create('https://example.com/affiche', 'affiche');

        $this->client->request('GET', 'https://go.example.com/affiche?utm=x', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15']);
        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.com/affiche', $response->headers->get('Location'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->client->request('GET', 'https://go.example.com/');
        self::assertSame('https://example.com/', $this->client->getResponse()->headers->get('Location'));

        $this->client->request('GET', 'https://go.example.com/nothing');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', 'https://go.example.com/affiche');
        self::assertSame(405, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', 'https://example.com/affiche');
        self::assertSame('a page of the site', $this->client->getResponse()->getContent());

        $this->client->request('GET', 'https://go.example.com/affiche', server: ['HTTP_USER_AGENT' => 'TelegramBot (like TwitterBot)']);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        self::assertSame([1, 1], [$this->read($link)->visits(), $this->read($link)->robotVisits()]);
    }

    public function testAClosedLinkIsGone(): void
    {
        $link = $this->create('https://example.com/', 'closed');
        $this->links()->enable((int) $link->id(), false);

        $this->client->request('GET', 'https://go.example.com/closed');
        self::assertSame(410, $this->client->getResponse()->getStatusCode());
    }

    public function testTheAdministrationIsForThoseGrantedTheRole(): void
    {
        $this->client->request('GET', '/admin/short-links/');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/short-links/', server: $this->reader());
        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/short-links/new', server: $this->reader());
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheAdministratorCreatesALinkThroughTheForm(): void
    {
        $crawler = $this->client->request('GET', '/admin/short-links/new', server: $this->admin());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $form = $crawler->selectButton('Save')->form();
        $this->client->submit($form, ['short_link[target]' => 'https://evil.example/', 'short_link[code]' => ''], $this->admin());
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('The host &quot;evil.example&quot; is not one a short link may send to.', (string) $this->client->getResponse()->getContent());

        $this->client->submit($form, ['short_link[target]' => 'https://example.com/fete', 'short_link[code]' => 'fete', 'short_link[locale]' => 'fr', 'short_link[maxVisits]' => '5'], $this->admin());
        self::assertSame(303, $this->client->getResponse()->getStatusCode());
        self::assertSame('/admin/short-links/?done=created', $this->client->getResponse()->headers->get('Location'));

        $link = $this->links()->get(1);
        self::assertSame(['fete', 'https://example.com/fete', 'fr', 5], [$link->code(), $link->target(), $link->locale(), $link->maxVisits()]);

        $this->client->request('GET', '/admin/short-links/?done=created', server: $this->admin());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('The short link was created.', $html);
        self::assertStringContainsString('https://lien.example.fr/fete', $html);
        self::assertStringContainsString('class="isTable"', $html);
        self::assertStringContainsString('0 / 5', $html);

        $this->client->request('GET', '/admin/short-links/?done=<script>', server: $this->admin());
        self::assertStringNotContainsString('<script>', (string) $this->client->getResponse()->getContent());
    }

    public function testTheAllowanceOfCreationsIsSpent(): void
    {
        for ($creation = 0; $creation < 3; ++$creation) {
            $this->post('https://example.com/'.$creation);
            self::assertSame(303, $this->client->getResponse()->getStatusCode());
        }

        $this->post('https://example.com/again');
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Too many short links were created; try again later.', (string) $this->client->getResponse()->getContent());
    }

    public function testTheAdministratorEditsTogglesAndRemovesALink(): void
    {
        $link = $this->create('https://example.com/', 'abcdef');
        $id = (int) $link->id();
        $crawler = $this->client->request('GET', '/admin/short-links/'.$id.'/edit', server: $this->admin());
        self::assertStringContainsString('https://go.example.com/abcdef', (string) $this->client->getResponse()->getContent());

        $this->client->submit($crawler->selectButton('Save')->form(), ['short_link[target]' => 'https://example.com/neuf', 'short_link[label]' => 'Neuf'], $this->admin());
        self::assertSame('/admin/short-links/?done=updated', $this->client->getResponse()->headers->get('Location'));
        self::assertSame(['abcdef', 'https://example.com/neuf', 'Neuf'], [$this->read($link)->code(), $this->read($link)->target(), $this->read($link)->label()]);

        $crawler = $this->client->request('GET', '/admin/short-links/', server: $this->admin());
        $token = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/short-links/'.$id.'/toggle', ['_token' => 'forged', 'enabled' => '0'], server: $this->admin());
        self::assertSame(400, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/admin/short-links/'.$id.'/toggle', ['_token' => $token, 'enabled' => '0'], server: $this->admin());
        self::assertSame('/admin/short-links/?done=disabled', $this->client->getResponse()->headers->get('Location'));
        self::assertFalse($this->read($link)->isEnabled());

        $this->client->request('POST', '/admin/short-links/'.$id.'/toggle', ['_token' => $token, 'enabled' => '1'], server: $this->admin());
        self::assertSame('/admin/short-links/?done=enabled', $this->client->getResponse()->headers->get('Location'));

        $this->client->request('POST', '/admin/short-links/'.$id.'/delete', ['_token' => $token], server: $this->admin());
        self::assertSame('/admin/short-links/?done=deleted', $this->client->getResponse()->headers->get('Location'));

        foreach (['POST /admin/short-links/'.$id.'/delete', 'POST /admin/short-links/'.$id.'/toggle', 'GET /admin/short-links/'.$id.'/edit', 'GET /admin/short-links/'.$id.'/qr-code.svg'] as $request) {
            [$method, $path] = explode(' ', $request);
            $this->client->request($method, $path, ['_token' => $token], server: $this->admin());
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $request);
        }
    }

    public function testTheQrCodeOfALink(): void
    {
        $link = $this->create('https://example.com/', 'qrcode');

        $this->client->request('GET', '/admin/short-links/'.$link->id().'/qr-code.svg', server: $this->admin());
        $response = $this->client->getResponse();
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertStringContainsString('https://go.example.com/qrcode', (string) $response->getContent());
        self::assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('short-link-qrcode.svg', (string) $response->headers->get('Content-Disposition'));

        $this->client->request('GET', '/admin/short-links/'.$link->id().'/qr-code.png', server: $this->admin());
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));

        $this->client->request('GET', '/admin/short-links/'.$link->id().'/qr-code.svg', server: $this->reader());
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheListSearchesAndPages(): void
    {
        for ($link = 1; $link <= ShortLinkAdminController::PAGE_SIZE + 1; ++$link) {
            $this->create('https://example.com/'.$link, 'code'.$link);
        }

        $this->client->request('GET', '/admin/short-links/', server: $this->admin());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Page 1 of 2', $html);
        self::assertStringContainsString('rel="next"', $html);

        $this->client->request('GET', '/admin/short-links/?page=2', server: $this->admin());
        self::assertStringContainsString('code1<', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/short-links/?q=code51', server: $this->admin());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('code51', $html);
        self::assertStringNotContainsString('code50', $html);
        self::assertStringNotContainsString('Page 1 of', $html);
    }

    private function post(string $target): void
    {
        $crawler = $this->client->request('GET', '/admin/short-links/new', server: $this->admin());
        $this->client->submit($crawler->selectButton('Save')->form(), ['short_link[target]' => $target], $this->admin());
    }

    private function create(string $target, string $code): ShortLink
    {
        $draft = new ShortLinkDraft();
        $draft->target = $target;
        $draft->code = $code;

        return $this->links()->create($draft, 'fixture:'.$code);
    }

    private function read(ShortLink $link): ShortLink
    {
        $entityManager = self::getContainer()->get('test.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        return $this->links()->get((int) $link->id());
    }

    private function links(): ShortLinks
    {
        $links = self::getContainer()->get(ShortLinks::class);
        self::assertInstanceOf(ShortLinks::class, $links);

        return $links;
    }

    /**
     * @return array<string, string>
     */
    private function admin(): array
    {
        return ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin'];
    }

    /**
     * @return array<string, string>
     */
    private function reader(): array
    {
        return ['PHP_AUTH_USER' => 'reader', 'PHP_AUTH_PW' => 'reader'];
    }
}
