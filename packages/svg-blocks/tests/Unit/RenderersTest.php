<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Tests\Unit;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\SvgWriter;
use Phil\SvgBlocks\Contract\MermaidService;
use Phil\SvgBlocks\Exception\InvalidConfiguration;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\SvgBlocks\Renderer\ChartRenderer;
use Phil\SvgBlocks\Renderer\MathRenderer;
use Phil\SvgBlocks\Renderer\MermaidRenderer;
use Phil\SvgBlocks\Renderer\QrCodeRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocks\Source;
use Phil\SvgChart\SvgChartWriter;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrCodeRenderer::class)]
#[CoversClass(ChartRenderer::class)]
#[CoversClass(MathRenderer::class)]
#[CoversClass(MermaidRenderer::class)]
#[CoversClass(Signer::class)]
#[CoversClass(Source::class)]
#[CoversClass(RenderingFailed::class)]
#[CoversClass(InvalidConfiguration::class)]
final class RenderersTest extends TestCase
{
    public function testDrawsAQrCode(): void
    {
        $svg = new QrCodeRenderer(new SvgWriter(0), ErrorCorrection::High)->render(new Block('q', 'qrCode', ['data' => 'ABC']));

        self::assertStringContainsString('viewBox="0 0 21 21"', $svg);
        self::assertStringContainsString('<title>ABC</title>', $svg);
        self::assertStringContainsString('viewBox="0 0 29 29"', new QrCodeRenderer()->render(new Block('q', 'qrCode', ['data' => 'ABC'])));
    }

    public function testRefusesAQrCodeWithoutText(): void
    {
        foreach ([[], ['data' => ''], ['data' => 3]] as $data) {
            try {
                new QrCodeRenderer()->render(new Block('q', 'qrCode', $data));
                self::fail('Refused.');
            } catch (RenderingFailed $failure) {
                self::assertSame('The QR code has no text.', $failure->getMessage());
            }
        }
    }

    public function testRefusesAQrCodeTooLong(): void
    {
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('more than a QR code of version 40 holds at level M');

        new QrCodeRenderer()->render(new Block('q', 'qrCode', ['data' => str_repeat('a', 3000)]));
    }

    public function testDrawsAChart(): void
    {
        $svg = new ChartRenderer(new SvgChartWriter(300, 200))->render(new Block('c', 'chart', ['type' => 'line', 'data' => ['labels' => ['a'], 'datasets' => [['data' => [1]]]]]));

        self::assertStringContainsString('viewBox="0 0 300 200"', $svg);
        self::assertStringContainsString('viewBox="0 0 640 400"', new ChartRenderer()->render(new Block('c', 'chart', ['type' => 'pie', 'data' => []])));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The chart value at "type" is invalid');

        new ChartRenderer()->render(new Block('c', 'chart', []));
    }

    public function testConvertsAFormula(): void
    {
        self::assertStringContainsString('display="block"><semantics><mrow><mi>x</mi></mrow>', new MathRenderer()->render(new Block('m', 'math', ['latex' => 'x'])));
        self::assertStringContainsString('<semantics><mrow></mrow>', new MathRenderer()->render(new Block('m', 'math', [])));

        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('"\nope" is not supported');

        new MathRenderer()->render(new Block('m', 'math', ['latex' => '\nope']));
    }

    public function testSanitizesWhatTheDiagramServiceAnswers(): void
    {
        $service = new class implements MermaidService {
            public ?string $code = null;

            public function toSvg(string $code): string
            {
                $this->code = $code;

                return '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="1" height="1"/></svg>';
            }
        };
        $svg = new MermaidRenderer($service)->render(new Block('d', 'mermaid', ['code' => 'graph TD; A-->B']));

        self::assertSame('graph TD; A-->B', $service->code);
        self::assertStringNotContainsString('script', $svg);
        self::assertStringNotContainsString('onload', $svg);
        self::assertStringContainsString('<rect', $svg);
    }

    /**
     * Mermaid writes its theme in a `<style>` element, each rule under the diagram's id: the rules are kept, placed
     * under the diagram itself, so that none reaches the page.
     */
    public function testKeepsTheThemeOfADiagramInsideIt(): void
    {
        $service = new class implements MermaidService {
            public function toSvg(string $code): string
            {
                return '<svg xmlns="http://www.w3.org/2000/svg" id="mermaid-1"><style>#mermaid-1 .node rect{fill:'.$code.'}body{display:none}</style><g class="node"><rect/></g></svg>';
            }
        };
        $renderer = new MermaidRenderer($service);

        $first = $renderer->render(new Block('d', 'mermaid', ['code' => '#ECECFF']));
        $second = $renderer->render(new Block('e', 'mermaid', ['code' => '#FFF4DD']));
        $scope = 'svg-drawing-'.substr(hash('sha256', $service->toSvg('#ECECFF')), 0, 16);

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$scope.'"><style>#'.$scope.' .node rect {'."\n".'  fill: #ECECFF;'."\n".'}'."\n\n"
            .'#'.$scope.' body {'."\n".'  display: none;'."\n".'}'."\n".'</style><g class="node"><rect/></g></svg>',
            $first,
        );
        self::assertStringNotContainsString($scope, $second, 'two diagrams on one page never share a rule, though Mermaid names both roots alike');
    }

    public function testRefusesADiagramWithoutCodeOrAnImageRefused(): void
    {
        $service = new class implements MermaidService {
            public function toSvg(string $code): string
            {
                return 'not an image';
            }
        };

        foreach ([[[], 'The diagram has no code.'], [['code' => " \n"], 'The diagram has no code.'], [['code' => 7], 'The diagram has no code.'], [['code' => 'graph'], 'The diagram service answered with an image the sanitizer refuses.']] as [$data, $message]) {
            try {
                new MermaidRenderer($service)->render(new Block('d', 'mermaid', $data));
                self::fail('Refused.');
            } catch (RenderingFailed $failure) {
                self::assertSame($message, $failure->getMessage());
            }
        }
    }

    public function testSignsTheTypeTheSourceAndTheMarkup(): void
    {
        $signer = new Signer(str_repeat('k', 32));
        $key = $signer->sign('math', 'x', '<math/>');

        self::assertMatchesRegularExpression('/^v1\.[A-Za-z0-9_-]{43}$/D', $key);
        self::assertSame($key, $signer->sign('math', 'x', '<math/>'));
        self::assertNotSame($key, $signer->sign('qrCode', 'x', '<math/>'));
        self::assertNotSame($key, $signer->sign('math', 'y', '<math/>'));
        self::assertNotSame($key, $signer->sign('math', 'x', '<math />'));
        self::assertNotSame($signer->sign('ma', "th\0x", '<math/>'), $signer->sign('mat', "h\0x", '<math/>'));
        self::assertNotSame($key, new Signer(str_repeat('j', 32))->sign('math', 'x', '<math/>'));
        self::assertSame('v1.'.sodium_bin2base64(hash_hmac('sha256', "math\0x\0<math/>", str_repeat('k', 32), true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), $key);

        self::assertTrue($signer->trusts(new Block('m', 'math', ['latex' => 'x']), '<math/>', $key));
        self::assertFalse($signer->trusts(new Block('m', 'math', ['latex' => 'y']), '<math/>', $key));
        self::assertFalse($signer->trusts(new Block('m', 'math', ['latex' => 'x']), '<math/>', $key.'x'));
    }

    public function testRefusesAShortSecret(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The secret of the drawing keys must be at least 32 bytes long.');

        new Signer(str_repeat('k', 31));
    }

    public function testReadsTheSourceOfEachType(): void
    {
        self::assertSame('x^2', Source::of(new Block('a', 'math', ['latex' => 'x^2', 'rendered' => []])));
        self::assertSame('graph', Source::of(new Block('a', 'mermaid', ['code' => 'graph', 'caption' => 'c'])));
        self::assertSame('text', Source::of(new Block('a', 'qrCode', ['data' => 'text'])));
        self::assertSame('', Source::of(new Block('a', 'qrCode', ['data' => 1])));
        self::assertSame('', Source::of(new Block('a', 'math', [])));
        self::assertSame('', Source::of(new Block('a', 'mermaid', [])));
        self::assertSame('{"type":"bar","v":1.0,"u":"é/"}', Source::of(new Block('a', 'chart', ['type' => 'bar', 'rendered' => ['markup' => 'x'], 'v' => 1.0, 'u' => 'é/'])));
        self::assertSame('{"list":[1,2]}', Source::of(new Block('a', 'chart', ['list' => [1, 2]])));
    }
}
