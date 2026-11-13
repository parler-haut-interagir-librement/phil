<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Functional;

use Override;
use Phil\UxEasyAdminBundle\Infrastructure\EasyAdmin\ThemeStylesheet;
use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;
use Phil\UxEasyAdminBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Pages of an EasyAdmin dashboard served by a kernel, and the stylesheet compiled by AssetMapper.
 */
#[CoversClass(PhilUxEasyAdminBundle::class)]
#[CoversClass(ThemeStylesheet::class)]
final class UxEasyAdminBundleTest extends WebTestCase
{
    /**
     * @var array<string, mixed>
     */
    private static array $config = [];

    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(FrameworkBundle::class)) {
            self::markTestSkipped('FrameworkBundle is installed for development only: no kernel can boot.');
        }

        self::$config = [];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(self::$config);
    }

    public function testEveryPageLinksTheThemeAfterTheDashboardsOwnStyles(): void
    {
        $html = $this->page(self::createClient(), '/admin');

        self::assertMatchesRegularExpression('#<link\s+rel="stylesheet" href="/assets/styles/admin-[\w-]+\.css">\s*<link\s+rel="stylesheet" href="/assets/phil-ux-easyadmin/phil-ux-easyadmin-[\w-]+\.css">#', $html);
    }

    public function testADashboardThatListsTheThemeLinksItOnce(): void
    {
        $html = $this->page(self::createClient(), '/listed');

        self::assertSame(1, preg_match_all('#href="/assets/phil-ux-easyadmin/phil-ux-easyadmin-[\w-]+\.css"#', $html));
    }

    public function testTheThemeCanBeLeftToTheDashboards(): void
    {
        self::$config = ['theme' => false];
        $client = self::createClient();

        self::assertStringNotContainsString('phil-ux-easyadmin', $this->page($client, '/admin'));
        self::assertMatchesRegularExpression('#href="/assets/phil-ux-easyadmin/phil-ux-easyadmin-[\w-]+\.css"#', $this->page($client, '/listed'));
    }

    public function testTheStylesheetIsAnAsset(): void
    {
        self::bootKernel();
        $mapper = self::getContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        $stylesheet = $mapper->getAsset(PhilUxEasyAdminBundle::STYLESHEET);

        self::assertNotNull($stylesheet);
        self::assertSame(realpath(PhilUxEasyAdminBundle::STYLES_DIR.'/phil-ux-easyadmin.css'), realpath($stylesheet->sourcePath));
        self::assertStringStartsWith('/assets/phil-ux-easyadmin/phil-ux-easyadmin-', $stylesheet->publicPath);
    }

    private function page(KernelBrowser $client, string $path): string
    {
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return (string) $client->getResponse()->getContent();
    }
}
