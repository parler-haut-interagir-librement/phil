<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Functional;

use Override;
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocksBundle\Infrastructure\Messenger\MarkupScheduler;
use Phil\SvgBlocksBundle\Tests\Functional\App\InMemoryDocuments;
use Phil\SvgBlocksBundle\Tests\Functional\App\MermaidResponses;
use Phil\SvgBlocksBundle\Tests\Functional\App\TestKernel;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;

#[CoversNothing]
final class SvgBlocksBundleTest extends WebTestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private KernelBrowser $browser;

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

        $this->browser = self::createClient();
    }

    public function testAFieldDrawsTheBlocksItReceivesAndTheRendererShowsThem(): void
    {
        $factory = self::getContainer()->get('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory->create(UjoType::class);
        $form->submit(json_encode(['blocks' => [
            ['id' => 'q', 'type' => 'qrCode', 'data' => ['data' => 'HI', 'rendered' => ['markup' => '<svg onload="x()"/>', 'key' => 'v1.forged']]],
            ['id' => 'm', 'type' => 'math', 'data' => ['latex' => '\frac12']],
            ['id' => 'c', 'type' => 'math', 'data' => ['latex' => '\newcommand{\R}{\mathbb{R}}\textcolor{red}{x\in\R}\tag{1}']],
            ['id' => 'h', 'type' => 'math', 'data' => ['latex' => '\ce{2H2 + O2 -> 2H2O}']],
            ['id' => 'd', 'type' => 'mermaid', 'data' => ['code' => 'graph TD; A-->B']],
        ]], \JSON_THROW_ON_ERROR));

        self::assertTrue($form->isSynchronized());
        $json = $form->getData();
        self::assertIsString($json);
        self::assertStringNotContainsString('forged', $json);
        self::assertStringContainsString('<mfrac><mn>1</mn><mn>2</mn></mfrac>', $json);
        self::assertStringContainsString('<mstyle mathcolor=\"#ff0000\"><mrow><mi>x</mi><mo>∈</mo><mi>ℝ</mi></mrow></mstyle>', $json, 'a macro and a colour');
        self::assertStringContainsString('<mtext>(1)</mtext>', $json, 'the number of the formula');
        self::assertStringContainsString('<msub><mi mathvariant=\"normal\">H</mi><mn>2</mn></msub>', $json, 'chemical notation');
        self::assertStringContainsString('viewBox=\"0 0 29 29\"', $json, 'a QR code at level H');

        $ujo = self::getContainer()->get(Ujo::class);
        self::assertInstanceOf(Ujo::class, $ujo);
        $html = $ujo->toHtml($json);

        self::assertStringContainsString('<figure class="ujo-qr-code"><svg', $html);
        self::assertStringContainsString('<div class="ujo-math"><math', $html);
        self::assertStringContainsString('<figure class="ujo-mermaid"><pre class="mermaid">', $html, 'the diagram waits for the background pass');
    }

    public function testAFieldCanLeaveItsDocumentsAlone(): void
    {
        $factory = self::getContainer()->get('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory->create(UjoType::class, null, ['svg_blocks' => false, 'document' => true]);
        $form->submit('{"blocks":[{"id":"m","type":"math","data":{"latex":"x"}}]}');

        $document = $form->getData();
        self::assertInstanceOf(Document::class, $document);
        self::assertArrayNotHasKey('rendered', $document->blocks[0]->data);
    }

    public function testTheBackgroundPassDrawsTheDiagrams(): void
    {
        $documents = self::getContainer()->get(InMemoryDocuments::class);
        $markup = self::getContainer()->get(BlockMarkup::class);
        $scheduler = self::getContainer()->get(MarkupScheduler::class);
        $mermaid = self::getContainer()->get(MermaidResponses::class);
        self::assertInstanceOf(InMemoryDocuments::class, $documents);
        self::assertInstanceOf(BlockMarkup::class, $markup);
        self::assertInstanceOf(MarkupScheduler::class, $scheduler);
        self::assertInstanceOf(MermaidResponses::class, $mermaid);

        $documents->documents['article:1'] = $markup->render(Document::fromArray(['blocks' => [['id' => 'd', 'type' => 'mermaid', 'data' => ['code' => 'graph TD', 'caption' => '']]]]))->document;
        $scheduler->schedule('article:1');
        $scheduler->schedule('article:gone');

        self::assertSame(['article:1'], $documents->saved);
        self::assertSame([['POST', 'http://mermaid:3000/render', '{"code":"graph TD"}']], $mermaid->requests);
        $stored = $documents->documents['article:1']->blocks[0]->data['rendered'] ?? null;
        self::assertIsArray($stored);
        $svg = $stored['markup'] ?? null;
        self::assertIsString($svg);
        self::assertStringContainsString('<text>A</text>', $svg);
        self::assertStringNotContainsString('script', $svg);
        self::assertStringContainsString('fill: #ececff;', $svg, 'the style of the diagram is kept');
        self::assertStringNotContainsString('example.com', $svg, 'its rules rewritten by the CSS sanitizer');

        $scheduler->schedule('article:1');
        self::assertSame(['article:1'], $documents->saved, 'a current drawing is kept without a save');
    }

    public function testTheControllerIsServedByAssetMapper(): void
    {
        $mapper = self::getContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        self::assertNotNull($mapper->getAsset('@phil/svg-blocks-bundle/preview_controller.js'));
        self::assertNotNull($mapper->getAsset('@phil/svg-blocks-bundle/lib/preview.js'));
    }

    public function testThePreviewDrawsABlockForItsAuthor(): void
    {
        $this->preview(['type' => 'math', 'data' => ['latex' => 'x^2']]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertStringContainsString('<msup><mi>x</mi><mn>2</mn></msup>', $this->answer()['markup'] ?? '');

        $this->preview(['type' => 'math', 'data' => ['latex' => '\def\a{\a}\a']]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('"\a" is used inside its own expansion', $this->answer()['error'] ?? '');

        $this->preview(['type' => 'math', 'data' => ['latex' => '\nope']]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('"\nope" is not supported', $this->answer()['error'] ?? '');

        $this->preview(['type' => 'paragraph', 'data' => []]);
        self::assertResponseStatusCodeSame(404);

        $this->preview(['type' => 'math']);
        self::assertResponseStatusCodeSame(400);

        $this->preview(['type' => 'math', 'data' => ['latex' => str_repeat('x', 70_000)]]);
        self::assertResponseStatusCodeSame(413);

        $this->preview(['type' => 'math', 'data' => ['latex' => 'x']], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);

        $this->browser->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $this->browser->request('POST', '/_svg-blocks/preview', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_CSRF_TOKEN' => self::TOKEN], content: '{"type":"math","data":{"latex":"x"}}');
        self::assertResponseStatusCodeSame(403, 'without the header of the controller');

        $this->browser->request('POST', '/_svg-blocks/preview', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PHIL_SVG_BLOCKS' => '1'], content: '{"type":"math","data":{"latex":"x"}}');
        self::assertResponseStatusCodeSame(403, 'without the token');
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $server
     */
    private function preview(array $body, array $server = []): void
    {
        $this->browser->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $this->browser->request('POST', '/_svg-blocks/preview', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PHIL_SVG_BLOCKS' => '1',
            'HTTP_CSRF_TOKEN' => self::TOKEN,
            ...$server,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string>
     */
    private function answer(): array
    {
        /** @var array<string, string> */
        return json_decode((string) $this->browser->getResponse()->getContent(), true, 8, \JSON_THROW_ON_ERROR);
    }
}
