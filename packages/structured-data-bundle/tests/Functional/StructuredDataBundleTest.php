<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Functional;

use Override;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Node;
use Phil\StructuredData\Validation\Validator;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;
use Phil\StructuredDataBundle\Tests\Functional\App\TelephoneProvider;
use Phil\StructuredDataBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PhilStructuredDataBundle::class)]
final class StructuredDataBundleTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testAPagePrintsOneGraphOfEveryPart(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/jeux/tournoi');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertSame(1, substr_count($html, '<script type="application/ld+json" nonce="bm9uY2U=">'));
        self::assertMatchesRegularExpression('#</script>\s*</body>#', $html, 'The element closes the body, once the content has added its nodes.');

        $graph = JsonLdReader::fromHtml($html)->graph;
        self::assertSame('en', $graph->language);
        self::assertSame(['https://example.com/#organization', 'https://example.com/#website', 'https://example.com/jeux/tournoi#webpage', 'https://example.com/jeux/tournoi#breadcrumb', 'https://example.com/jeux/tournoi#event', null], array_map(static fn (Node $node): ?string => $node->id, $graph->nodes()));
        $organization = $graph->get('https://example.com/#organization');
        $webPage = $graph->get('https://example.com/jeux/tournoi#webpage');
        self::assertInstanceOf(Node::class, $organization);
        self::assertInstanceOf(Node::class, $webPage);
        self::assertSame(['+15145550100', 'https://example.com/logo.png'], [$organization->get('telephone'), $organization->get('logo')]);
        self::assertSame(['@id' => 'https://example.com/jeux/tournoi#breadcrumb'], $webPage->toArray()['breadcrumb'] ?? null);
        self::assertSame([], new Validator()->validate($graph)->errors());
    }

    public function testAPageIsSharedFromItsGraphAndItsProvidersAreAskedOnce(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->disableReboot();
        $client->request('GET', '/partage');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<meta property="og:title" content="Le titre du modèle">', $html);
        self::assertStringContainsString('<meta property="og:description" content="Le résumé">', $html);
        self::assertStringContainsString('<meta property="og:image" content="https://example.com/image.png">', $html);

        $provider = self::getContainer()->get(TelephoneProvider::class);
        self::assertInstanceOf(TelephoneProvider::class, $provider);
        self::assertSame(1, $provider->calls);
    }

    public function testWhatTheRequestSetsWinsOverTheGraph(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/partage?title=Choisi');

        self::assertStringContainsString('<meta property="og:title" content="Choisi">', (string) $client->getResponse()->getContent());
    }

    public function testAWorkerServesEachPageItsOwnGraph(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->disableReboot();

        $client->request('GET', '/jeux/tournoi');
        self::assertStringContainsString('Tournoi de printemps', (string) $client->getResponse()->getContent());

        $client->request('GET', '/plain');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Tournoi de printemps', $html);
        self::assertStringNotContainsString('nonce=', $html);
        self::assertSame(['https://example.com/#organization', 'https://example.com/#website', 'https://example.com/plain#webpage'], array_map(static fn (Node $node): ?string => $node->id, JsonLdReader::fromHtml($html)->graph->nodes()));
    }

    public function testAPageThatShowsAWithdrawnRecordPrintsNoStructuredData(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->disableReboot();

        $client->request('GET', '/jeux/prive');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<h1>Tournoi de printemps</h1>', $html);
        self::assertStringNotContainsString('application/ld+json', $html);

        $client->request('GET', '/plain');
        self::assertStringContainsString('application/ld+json', (string) $client->getResponse()->getContent(), 'The next page of the worker prints its own.');
    }

    public function testTheCommandChecksASavedPage(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']);
        $client->request('GET', '/jeux/tournoi');

        $file = sys_get_temp_dir().'/phil-structured-data-page-'.bin2hex(random_bytes(4)).'.html';
        file_put_contents($file, (string) $client->getResponse()->getContent());

        try {
            $tester = new CommandTester(new Application(self::bootKernel())->find('phil:structured-data:check'));
            self::assertSame(0, $tester->execute(['page' => $file]));
            self::assertStringContainsString('6 nodes in 1 JSON-LD elements: 0 errors', $tester->getDisplay());
        } finally {
            unlink($file);
        }
    }
}
