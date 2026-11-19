<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

/**
 * An image to write into a word processing document: its bytes, media type and extension, its size in pixels read
 * from its bytes — 400 × 300 when they do not say —, and its text alternative.
 *
 * @internal
 */
final readonly class Picture
{
    public int $width;

    public int $height;

    public function __construct(
        public string $bytes,
        public string $mediaType,
        public string $alt,
    ) {
        [$width, $height] = $this->size($bytes, $mediaType);
        $this->width = max(1, min(20_000, $width));
        $this->height = max(1, min(20_000, $height));
    }

    public function extension(): string
    {
        return match ($this->mediaType) {
            'image/svg+xml' => 'svg',
            default => substr($this->mediaType, 6),
        };
    }

    /**
     * The size shown, in inches: the image at 96 pixels an inch, no wider than the given width.
     *
     * @return array{float, float}
     */
    public function inches(float $widest = 6.0): array
    {
        $width = $this->width / 96;
        $height = $this->height / 96;
        $scale = min(1.0, $widest / $width);

        return [$width * $scale, $height * $scale];
    }

    /**
     * @return array{int, int}
     */
    private function size(string $bytes, string $mediaType): array
    {
        return match ($mediaType) {
            'image/png' => \strlen($bytes) >= 24 ? [$this->big($bytes, 16, 4), $this->big($bytes, 20, 4)] : [400, 300],
            'image/gif' => \strlen($bytes) >= 10 ? [\ord($bytes[6]) | (\ord($bytes[7]) << 8), \ord($bytes[8]) | (\ord($bytes[9]) << 8)] : [400, 300],
            'image/jpeg' => $this->jpeg($bytes),
            default => [400, 300],
        };
    }

    /**
     * The size a JPEG gives in its start-of-frame segment.
     *
     * @return array{int, int}
     */
    private function jpeg(string $bytes): array
    {
        $at = 2;
        $length = \strlen($bytes);

        while ($at + 8 < $length && "\xFF" === $bytes[$at]) {
            $marker = \ord($bytes[$at + 1]);
            $size = $this->big($bytes, $at + 2, 2);

            if ($marker >= 0xC0 && $marker <= 0xCF && !\in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                return [$this->big($bytes, $at + 7, 2), $this->big($bytes, $at + 5, 2)];
            }

            $at += 2 + max(2, $size);
        }

        return [400, 300];
    }

    /**
     * The unsigned big-endian integer of a few bytes.
     */
    private function big(string $bytes, int $at, int $length): int
    {
        $value = 0;

        for ($byte = 0; $byte < $length; ++$byte) {
            $value = ($value << 8) | \ord(($bytes[$at + $byte] ?? "\0")[0]);
        }

        return $value;
    }
}
