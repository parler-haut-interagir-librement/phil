<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Tests\Integration;

use Override;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\SvgBlocks\Http\HttpMermaidService;
use Phil\SvgBlocks\Renderer\MermaidRenderer;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Against the Mermaid service of phil/svg-blocks-bundle's container image, at PHIL_MERMAID_URL; skipped without it.
 */
#[CoversClass(HttpMermaidService::class)]
#[CoversClass(MermaidRenderer::class)]
final class MermaidServiceTest extends TestCase
{
    private string $url;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv('PHIL_MERMAID_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('PHIL_MERMAID_URL names no Mermaid service.');
        }

        $this->url = $url;
    }

    public function testDrawsADiagramWhoseLabelsSurviveTheSanitizer(): void
    {
        $svg = new MermaidRenderer(new HttpMermaidService(HttpClient::create(), $this->url))->render(new Block('d', 'mermaid', ['code' => 'graph TD; A[Start] --> B[End]']));

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('Start', $svg);
        self::assertStringContainsString('End', $svg);
        self::assertStringNotContainsString('foreignObject', $svg);
    }

    public function testReportsMermaidsReason(): void
    {
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('Mermaid refuses the diagram: Parse error');

        new HttpMermaidService(HttpClient::create(), $this->url)->toSvg('graph TD; A-->');
    }
}
