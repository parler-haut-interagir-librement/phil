<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;

/**
 * The QR code of a short link, drawn by phil/qr-code: the link's address on the domain of its locale, as an SVG image
 * or a PNG file. A short address keeps the code small and easy to scan.
 *
 * It keeps no state: the same service serves every request.
 */
final readonly class ShortLinkQrCodes
{
    /**
     * @throws InvalidOption when a colour, the quiet zone, the size or the scale is out of its range
     */
    public function __construct(
        private ShortLinks $links,
        private ErrorCorrection $level = ErrorCorrection::Medium,
        private int $quietZone = 4,
        private string $dark = '#000000',
        private string $light = '#ffffff',
        private int $size = 256,
        private int $scale = 8,
    ) {
        // The writers check their options: building them once here fails the container, not a request.
        new SvgWriter($quietZone, $dark, $light, $size);
        new PngWriter($scale, $quietZone, $dark, $light);
    }

    public function svg(ShortLink $link): string
    {
        $url = $this->links->url($link);

        return new SvgWriter($this->quietZone, $this->dark, $this->light, $this->size)->write(QrCode::encode($url, $this->level), $url);
    }

    public function png(ShortLink $link): string
    {
        return new PngWriter($this->scale, $this->quietZone, $this->dark, $this->light)->write(QrCode::encode($this->links->url($link), $this->level));
    }
}
