<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Functional;

use Override;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\LegalNoticesBundle\Infrastructure\Twig\KitComponents;
use Phil\LegalNoticesBundle\Infrastructure\Twig\LegalNoticesExtension;
use Phil\LegalNoticesBundle\PhilLegalNoticesBundle;
use Phil\LegalNoticesBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The pages rendered by the application's Twig, their tables and badges drawn by the recipes of the PHIL UX kit or of
 * the Shadcn kit of the Symfony UX Toolkit, installed as the Toolkit would, in a subdirectory of the components or at
 * their root.
 */
#[CoversClass(PhilLegalNoticesBundle::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(LegalNoticesExtension::class)]
final class LegalNoticesBundleTest extends WebTestCase
{
    private static string $subdirectory = 'ui';

    /** @var TestKernel::PHIL|TestKernel::SHADCN */
    private static string $kit = TestKernel::PHIL;

    #[Override]
    protected function setUp(): void
    {
        self::$subdirectory = 'ui';
        self::$kit = TestKernel::PHIL;
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(TestKernel::SHADCN === self::$kit ? 'test_shadcn' : 'test', true, self::$subdirectory, self::$kit);
    }

    /**
     * @return iterable<string, array{TestKernel::PHIL|TestKernel::SHADCN, string}>
     */
    public static function kits(): iterable
    {
        yield 'the PHIL UX kit in the subdirectory of the Toolkit' => [TestKernel::PHIL, 'ui'];
        yield 'the PHIL UX kit at the root of the components' => [TestKernel::PHIL, ''];
        yield 'the Shadcn kit in the subdirectory of the Toolkit' => [TestKernel::SHADCN, 'ui'];
        yield 'the Shadcn kit at the root of the components' => [TestKernel::SHADCN, ''];
    }

    /**
     * @param TestKernel::PHIL|TestKernel::SHADCN $kit
     */
    #[DataProvider('kits')]
    public function testThePartsAreDrawnByTheRecipesOfTheKit(string $kit, string $subdirectory): void
    {
        $this->useKit($kit, $subdirectory);
        $client = self::createClient();

        $client->request('GET', '/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-slot="badge"][data-variant="default"]', 'In force since 2026-01-01');
        self::assertSelectorCount(1, '[data-slot="table-container"] > table');
        self::assertSelectorCount(1, 'thead[data-slot="table-header"]');
        self::assertSelectorCount(2, 'thead [data-slot="table-head"][scope="col"]');
        self::assertSelectorCount(3, 'tbody [data-slot="table-row"]');
        self::assertSelectorTextSame('tbody [data-slot="table-row"]:first-child [data-slot="table-cell"]:first-child', 'Your account');
        self::assertSelectorTextSame('tbody [data-slot="table-row"]:first-child [data-slot="table-cell"]:last-child', '2 years');

        $client->request('GET', '/legal/history');
        self::assertSelectorTextSame('li [data-slot="badge"]', 'in force');

        $client->request('GET', '/legal/v2');
        self::assertSelectorTextSame('[data-slot="badge"][data-variant="outline"]', 'Takes effect on 2099-01-01');
    }

    /**
     * Every page, in each locale, rendered whole by the recipes of the kit.
     *
     * @param TestKernel::PHIL|TestKernel::SHADCN $kit
     */
    #[DataProvider('kits')]
    public function testEveryPageRendersWithTheKit(string $kit, string $subdirectory): void
    {
        $this->useKit($kit, $subdirectory);
        $client = self::createClient();

        foreach (['', '/fr'] as $prefix) {
            foreach (['/legal', '/legal/history', '/legal/v1', '/legal/v2', '/privacy', '/privacy/history', '/privacy/v1'] as $path) {
                $client->request('GET', $prefix.$path);
                self::assertResponseIsSuccessful($prefix.$path);
                self::assertSelectorExists('main h1');
                self::assertSelectorExists('[data-slot="badge"]');
                self::assertSelectorCount(2, 'footer nav li a');
            }
        }

        $client->request('GET', '/fr/privacy');
        self::assertSelectorTextSame('[data-slot="badge"][data-variant="default"]', 'En vigueur depuis le 2026-01-01');
        self::assertSelectorTextSame('tbody [data-slot="table-row"]:first-child [data-slot="table-cell"]:first-child', 'Votre compte');
    }

    public function testThePagesInEachLocale(): void
    {
        $client = self::createClient();

        $client->request('GET', '/legal');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', 'en');
        self::assertSelectorTextSame('h1', 'Legal notice');
        $page = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<strong>Example Inc.</strong>', $page);
        self::assertStringContainsString('Version 1', $page);
        self::assertStringContainsString('In force since 2026-01-01', $page);
        self::assertStringContainsString('Version 2 takes effect on 2099-01-01', $page);
        self::assertStringContainsString('<a href="/legal">Legal notice</a>', $page);
        self::assertStringContainsString('<a href="/privacy">Privacy policy</a>', $page);

        $client->request('GET', '/fr/legal');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Mentions légales');
        $french = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Ce site est publié par <strong>Example Inc.</strong>, 1 Main Street<br>', $french);
        self::assertStringContainsString('Example &lt;Hosting&gt;', $french);
        self::assertStringContainsString('<a href="/fr/privacy">Politique de confidentialité</a>', $french);
        self::assertStringContainsString('En vigueur depuis le 2026-01-01', $french);
    }

    public function testThePrivacyPolicyListsTheConsentCategories(): void
    {
        $client = self::createClient();

        $client->request('GET', '/privacy');
        self::assertResponseIsSuccessful();
        $page = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Write to privacy@example.com. Audience: Audience measurement.', $page);
        self::assertStringContainsString('Necessary · necessary, set without your consent', $page);
        self::assertStringContainsString('Cookies: _pk_id.*, _pk_ses.*, and _pk_ref.*', $page);
        self::assertStringContainsString('Cookies: PHPSESSID', $page);
        self::assertStringContainsString('How long we keep your information', $page);

        $client->request('GET', '/fr/privacy');
        $french = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Nécessaires · nécessaires, déposés sans votre consentement', $french);
        self::assertStringContainsString('_pk_id.*, _pk_ses.* et _pk_ref.*', $french);
        self::assertStringContainsString('Votre compte', $french);
    }

    public function testTheHistoryAndTheVersions(): void
    {
        $client = self::createClient();

        $client->request('GET', '/legal/history');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Versions of the Legal notice');
        self::assertSelectorCount(2, 'ol li');
        self::assertStringContainsString('A new host.', (string) $client->getResponse()->getContent());

        $client->request('GET', '/legal/v2');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Takes effect on 2099-01-01', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('What changed: A new host.', (string) $client->getResponse()->getContent());

        $client->request('GET', '/fr/legal/v2');
        self::assertResponseIsSuccessful();

        foreach (['/legal/v3', '/legal/v9', '/privacy/v0', '/terms'] as $missing) {
            $client->request('GET', $missing);
            self::assertResponseStatusCodeSame(404, $missing);
        }
    }

    /**
     * @param TestKernel::PHIL|TestKernel::SHADCN $kit
     */
    private function useKit(string $kit, string $subdirectory): void
    {
        if (null === TestKernel::kitDirectory($kit)) {
            self::markTestSkipped(\sprintf('The %s kit is not installed.', $kit));
        }

        self::$kit = $kit;
        self::$subdirectory = $subdirectory;
    }

    public function testTheCheckPasses(): void
    {
        $kernel = self::bootKernel();
        $tester = new CommandTester(new Application($kernel)->find('phil:legal-notices:check'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('The public legal documents lack nothing.', $tester->getDisplay());
    }
}
