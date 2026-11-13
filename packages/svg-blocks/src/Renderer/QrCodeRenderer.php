<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Renderer;

use Override;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\QrCodeException;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;

/**
 * The SVG of a `qrCode` block's text, drawn by `phil/qr-code`.
 */
final readonly class QrCodeRenderer implements MarkupRenderer
{
    public function __construct(
        private SvgWriter $writer = new SvgWriter(),
        private ErrorCorrection $level = ErrorCorrection::Medium,
    ) {
    }

    #[Override]
    public function render(Block $block): string
    {
        $data = $block->data['data'] ?? null;

        if (!\is_string($data) || '' === $data) {
            throw RenderingFailed::because('The QR code has no text.');
        }

        try {
            return $this->writer->write(QrCode::encode($data, $this->level));
        } catch (QrCodeException $qrCodeException) {
            throw RenderingFailed::because($qrCodeException->getMessage(), $qrCodeException);
        }
    }
}
