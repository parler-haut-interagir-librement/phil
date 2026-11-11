<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle\Tests\Functional;

use Override;
use Phil\TextDiff\TextDiff;
use Phil\TextDiffBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

#[CoversNothing]
final class TextDiffBundleTest extends KernelTestCase
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

        self::bootKernel();
    }

    public function testTheFiltersRenderDifferences(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('page.html.twig', [
            'old' => "one\ntwo <b>",
            'new' => "one\ntwo <i>",
            'html' => '<p>Hello <b>big</b> world</p>',
            'changed' => '<p>Hello <i>small</i> world</p>',
            'document' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"Hello world"}}]}',
            'revised' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"Hello big world"}},{"id":"b","type":"quote","data":{"text":"New","caption":""}}]}',
        ]);

        self::assertSame(<<<'HTML'
            <pre class="text-diff">one
            <del>two &lt;b&gt;</del><ins>two &lt;i&gt;</ins></pre>
            <p>one
            two &lt;<del>b</del><ins>i</ins>&gt;</p>
            <div><p>Hello <del>big</del><i><ins>small</ins></i> world</p></div>
            <div><p>Hello <del>big</del><i><ins>small</ins></i> world</p></div>
            <article><div class="text-diff-block text-diff-changed"><p class="ujo-paragraph">Hello <ins>big </ins>world</p></div>
            <div class="text-diff-block text-diff-added"><ins><figure class="ujo-quote"><blockquote>New</blockquote></figure></ins></div>
            </article>

            HTML, $html);
    }

    public function testTheService(): void
    {
        $differ = $this->service(TextDiff::class);
        self::assertInstanceOf(TextDiff::class, $differ);

        self::assertSame('<del>a b c</del><ins>x y z</ins>', $differ->toHtml($differ->words('a b c', 'x y z')));
    }

    public function testTheControllerAndTheStylesheetAreServedByAssetMapper(): void
    {
        $mapper = $this->service('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        $controller = $mapper->getAsset('@phil/text-diff-bundle/comparison_controller.js');
        self::assertNotNull($controller);
        self::assertNotNull($mapper->getAsset('@phil/text-diff-bundle/lib/marks.js'));

        $stylesheet = $mapper->getAsset('phil-text-diff/text-diff.css');
        self::assertNotNull($stylesheet);
        self::assertStringStartsWith('/assets/phil-text-diff/text-diff-', $stylesheet->publicPath);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
