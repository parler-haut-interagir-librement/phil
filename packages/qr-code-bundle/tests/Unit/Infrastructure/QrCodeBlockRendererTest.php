<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\QrCodeBundle\Application\QrCodeDefaults;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\Ujo\QrCodeBlockRenderer;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrCodeBlockRenderer::class)]
final class QrCodeBlockRendererTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!interface_exists(MarkupRenderer::class)) {
            self::markTestSkipped('phil/svg-blocks is not installed: the qrCode block of UJO documents is an optional integration.');
        }
    }

    public function testDrawsTheTextOfTheBlock(): void
    {
        $codes = new QrCodes();

        self::assertSame($codes->svg('HELLO'), new QrCodeBlockRenderer($codes)->render(new Block('q', 'qrCode', ['data' => 'HELLO'])));
    }

    public function testRefusesABlockWithoutText(): void
    {
        foreach ([[], ['data' => ''], ['data' => 3]] as $data) {
            try {
                new QrCodeBlockRenderer(new QrCodes())->render(new Block('q', 'qrCode', $data));
                self::fail('A block without text is refused.');
            } catch (RenderingFailed $renderingFailed) {
                self::assertSame('The QR code has no text.', $renderingFailed->getMessage());
            }
        }
    }

    public function testReportsATextTheCodeCannotHold(): void
    {
        $this->expectException(RenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('The content of a QR code must be at most 4 bytes.');

        new QrCodeBlockRenderer(new QrCodes(new QrCodeDefaults(maxBytes: 4)))->render(new Block('q', 'qrCode', ['data' => 'HELLO']));
    }
}
