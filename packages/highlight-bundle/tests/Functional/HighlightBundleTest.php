<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Functional;

use Override;
use Phil\Highlight\Highlighter;
use Phil\HighlightBundle\Tests\Functional\App\TestKernel;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

#[CoversNothing]
final class HighlightBundleTest extends KernelTestCase
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

    public function testTemplatesAndUjoDocumentsAreColoured(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('page.html.twig', [
            'code' => 'echo "<b>";',
            'document' => '{"blocks":[{"id":"a","type":"code","data":{"code":"$a = 1;","language":"php"}},{"id":"b","type":"code","data":{"code":"<x>","language":"cobol"}}]}',
        ]);

        self::assertSame(<<<'HTML'
            <pre><code class="language-php"><span class="hl-keyword">echo</span> <span class="hl-string">&quot;&lt;b&gt;&quot;</span><span class="hl-punctuation">;</span></code></pre>
            <pre><code class="language-vue"><span class="hl-punctuation">&lt;</span><span class="hl-tag">p</span><span class="hl-punctuation">&gt;</span></code></pre>
            <pre><code>echo &quot;&lt;b&gt;&quot;;</code></pre>
            <p><span class="hl-keyword">hey</span></p>
            <pre><code class="language-php"><span class="hl-line hl-line-inserted"><span class="hl-line-number">10</span><span class="hl-keyword">echo</span> <span class="hl-number">1</span><span class="hl-punctuation">;</span></span>
            <span class="hl-line hl-line-highlighted"><span class="hl-line-number">11</span><span class="hl-keyword">echo</span> <span class="hl-number">2</span><span class="hl-punctuation">;</span></span></code></pre>
            <article><pre class="ujo-code"><code class="language-php"><span class="hl-variable">$a</span> <span class="hl-operator">=</span> <span class="hl-number">1</span><span class="hl-punctuation">;</span></code></pre>
            <pre class="ujo-code"><code class="language-cobol">&lt;x&gt;</code></pre></article>

            HTML, $html);
    }

    public function testTheServices(): void
    {
        $highlighter = $this->service(Highlighter::class);
        self::assertInstanceOf(Highlighter::class, $highlighter);
        $ujo = $this->service(Ujo::class);
        self::assertInstanceOf(Ujo::class, $ujo);

        self::assertTrue($highlighter->supports('shout'));
        self::assertSame('html', $highlighter->language('vue')->name());
        self::assertSame('<pre class="ujo-code"><code class="language-yell"><span class="hl-keyword">a</span></code></pre>', $ujo->toHtml('{"blocks":[{"id":"a","type":"code","data":{"code":"a","language":"yell"}}]}'));
    }

    public function testTheStylesheetIsServedByAssetMapper(): void
    {
        $mapper = $this->service('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        $asset = $mapper->getAsset('phil-highlight/highlight.css');
        self::assertNotNull($asset);
        self::assertStringStartsWith('/assets/phil-highlight/highlight-', $asset->publicPath);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
