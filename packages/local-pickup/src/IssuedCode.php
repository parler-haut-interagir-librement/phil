<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;
use SensitiveParameter;

/**
 * A code just issued: the only moment the code itself exists, to be sent to the customer. The pickup keeps its digest.
 */
final readonly class IssuedCode
{
    public function __construct(
        public string $order,
        #[SensitiveParameter]
        public string $code,
        public string $digest,
    ) {
    }

    /**
     * The code as people read it: two groups of four, such as "7KQ2-M9XD".
     */
    public function display(): string
    {
        return substr($this->code, 0, 4).'-'.substr($this->code, 4);
    }

    /**
     * What the QR code holds: the order number and the code, separated by a colon, which ScannedCode reads back.
     */
    public function payload(): string
    {
        return $this->order.':'.$this->code;
    }

    public function qrCode(ErrorCorrection $level = ErrorCorrection::Medium): QrCode
    {
        return QrCode::encode($this->payload(), $level);
    }

    /**
     * The QR code as an SVG image, titled for screen readers.
     */
    public function svg(string $title = 'Pickup code', ?SvgWriter $writer = null): string
    {
        return ($writer ?? new SvgWriter())->write($this->qrCode(), $title);
    }

    /**
     * @return array{order: string, code: string}
     */
    public function __debugInfo(): array
    {
        return ['order' => $this->order, 'code' => '********'];
    }
}
