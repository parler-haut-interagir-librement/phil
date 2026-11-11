<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\Ujo;

use Override;
use Phil\QrCode\Exception\QrCodeException;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;

/**
 * The `qrCode` blocks of UJO (Universal JSON Output) documents drawn by phil/qr-code with the defaults of this
 * bundle — level, quiet zone, colours, size, ECI —: phil/svg-blocks-bundle keeps the SVG with the block and signs it,
 * so pages and print engines show it without a script.
 */
final readonly class QrCodeBlockRenderer implements MarkupRenderer
{
    public function __construct(
        private QrCodes $codes,
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
            return $this->codes->svg($data);
        } catch (QrCodeException $qrCodeException) {
            throw RenderingFailed::because($qrCodeException->getMessage(), $qrCodeException);
        }
    }
}
