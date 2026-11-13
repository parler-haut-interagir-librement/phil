<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Image;

use Phil\PagedMedia\Exception\InvalidImage;
use Phil\PagedMedia\Pdf\Bytes;

/**
 * A raster image ready to be placed in a document: a JPEG kept as it is, or a PNG whose compressed rows are kept as
 * they are when it has no transparency, and decoded into a colour image and an alpha mask when it has.
 */
final readonly class Image
{
    /**
     * The most pixels a transparent PNG may declare: its rows are decoded in memory to split the colour from the alpha,
     * so a larger header is refused before anything is allocated or inflated.
     */
    public const int MOST_TRANSPARENT_PIXELS = 36_000_000;

    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";

    /**
     * @param string      $colorSpace       the PDF colour space: `/DeviceGray`, `/DeviceRGB`, `/DeviceCMYK` or an indexed array
     * @param string      $filter           `DCTDecode` or `FlateDecode`
     * @param string|null $decodeParameters the dictionary of the filter's parameters, if it has any
     * @param string|null $alpha            the alpha channel, compressed, one byte or two per pixel
     * @param string|null $decode           the decode array of an inverted CMYK JPEG
     */
    private function __construct(
        public int $width,
        public int $height,
        public string $colorSpace,
        public int $bitsPerComponent,
        public string $filter,
        public string $data,
        public ?string $decodeParameters = null,
        public ?string $alpha = null,
        public ?string $decode = null,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if (false === $bytes) {
            throw InvalidImage::because(\sprintf('The image file "%s" cannot be read.', $path));
        }

        return self::fromBytes($bytes);
    }

    public static function fromBytes(string $bytes): self
    {
        if (str_starts_with($bytes, "\xFF\xD8")) {
            return self::jpeg($bytes);
        }

        if (str_starts_with($bytes, self::PNG_SIGNATURE)) {
            return self::png($bytes);
        }

        throw InvalidImage::because('An image is a JPEG or a PNG file.');
    }

    private static function jpeg(string $bytes): self
    {
        $at = 2;
        $adobe = false;

        while ($at + 4 <= \strlen($bytes)) {
            if ("\xFF" !== $bytes[$at]) {
                throw InvalidImage::because('The JPEG file is corrupt: a segment does not start with a marker.');
            }

            $marker = \ord($bytes[$at + 1]);
            $length = Bytes::readUint16($bytes, $at + 2);

            if (0xEE === $marker && 'Adobe' === substr($bytes, $at + 4, 5)) {
                $adobe = true;
            }

            // The start-of-frame markers, except the huffman and arithmetic table definitions that share the range.
            if ($marker >= 0xC0 && $marker <= 0xCF && !\in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                if ($at + 10 > \strlen($bytes)) {
                    break;
                }

                $components = \ord($bytes[$at + 9]);
                $colorSpace = match ($components) {
                    1 => '/DeviceGray',
                    3 => '/DeviceRGB',
                    4 => '/DeviceCMYK',
                    default => throw InvalidImage::because(\sprintf('A JPEG has 1, 3 or 4 colour components; this one has %d.', $components)),
                };

                return new self(
                    Bytes::readUint16($bytes, $at + 7),
                    Bytes::readUint16($bytes, $at + 5),
                    $colorSpace,
                    8,
                    'DCTDecode',
                    $bytes,
                    decode: 4 === $components && $adobe ? '[1 0 1 0 1 0 1 0]' : null,
                );
            }

            $at += 2 + $length;
        }

        throw InvalidImage::because('The JPEG file has no frame header.');
    }

    private static function png(string $bytes): self
    {
        $at = 8;
        $header = null;
        $palette = '';
        $compressed = '';

        while ($at + 8 <= \strlen($bytes)) {
            $length = Bytes::readUint32($bytes, $at);
            $type = substr($bytes, $at + 4, 4);
            $chunk = substr($bytes, $at + 8, $length);

            if (\strlen($chunk) !== $length) {
                throw InvalidImage::because('The PNG file is truncated.');
            }

            match ($type) {
                'IHDR' => $header = $chunk,
                'PLTE' => $palette = $chunk,
                'IDAT' => $compressed .= $chunk,
                default => null,
            };

            if ('IEND' === $type) {
                break;
            }

            $at += 12 + $length;
        }

        if (null === $header || 13 !== \strlen($header) || '' === $compressed) {
            throw InvalidImage::because('The PNG file has no header or no image data.');
        }

        $info = ['width' => Bytes::readUint32($header, 0), 'height' => Bytes::readUint32($header, 4), 'depth' => \ord($header[8]), 'color' => \ord($header[9]), 'interlace' => \ord($header[12])];

        if (1 === $info['interlace']) {
            throw InvalidImage::because('An interlaced PNG cannot be placed; save it without interlacing.');
        }

        $colorSpace = match ($info['color']) {
            0, 4 => '/DeviceGray',
            2, 6 => '/DeviceRGB',
            3 => self::indexed($palette),
            default => throw InvalidImage::because(\sprintf('The PNG colour type %d does not exist.', $info['color'])),
        };

        if (\in_array($info['color'], [4, 6], true)) {
            $sampleBytes = match ($info['depth']) {
                8 => 1,
                16 => 2,
                default => throw InvalidImage::because(\sprintf('A transparent PNG has 8 or 16 bits per sample; this one has %d.', $info['depth'])),
            };

            return self::withAlpha($info['width'], $info['height'], $info['depth'], 4 === $info['color'] ? 2 * $sampleBytes : 4 * $sampleBytes, $sampleBytes, $colorSpace, $compressed);
        }

        return new self(
            $info['width'],
            $info['height'],
            $colorSpace,
            $info['depth'],
            'FlateDecode',
            $compressed,
            \sprintf('<< /Predictor 15 /Colors %d /BitsPerComponent %d /Columns %d >>', 2 === $info['color'] ? 3 : 1, $info['depth'], $info['width']),
        );
    }

    private static function indexed(string $palette): string
    {
        if ('' === $palette || 0 !== \strlen($palette) % 3) {
            throw InvalidImage::because('An indexed PNG has no valid palette.');
        }

        return \sprintf('[/Indexed /DeviceRGB %d <%s>]', intdiv(\strlen($palette), 3) - 1, Bytes::hex($palette));
    }

    /**
     * Decodes the rows of a PNG with an alpha channel, and splits each pixel into its colour and its alpha.
     *
     * @param int<2, 8> $pixelBytes
     * @param int<1, 2> $sampleBytes
     */
    private static function withAlpha(int $width, int $height, int $depth, int $pixelBytes, int $sampleBytes, string $colorSpace, string $compressed): self
    {
        if (0 === $width || 0 === $height || $width * $height > self::MOST_TRANSPARENT_PIXELS) {
            throw InvalidImage::because(\sprintf('A transparent PNG has from 1 to %d pixels.', self::MOST_TRANSPARENT_PIXELS));
        }

        $rowBytes = $width * $pixelBytes;
        $raw = @gzuncompress($compressed, $height * ($rowBytes + 1));

        if (false === $raw) {
            throw InvalidImage::because('The image data of the PNG file cannot be decompressed within its size.');
        }

        $colour = '';
        $alpha = '';
        $previous = str_repeat("\0", $rowBytes);

        for ($row = 0; $row < $height; ++$row) {
            $start = $row * ($rowBytes + 1);
            $line = substr($raw, $start + 1, $rowBytes);

            if (\strlen($line) !== $rowBytes) {
                throw InvalidImage::because('The image data of the PNG file is shorter than its size.');
            }

            $line = self::unfilter(\ord($raw[$start]), $line, $previous, $pixelBytes);
            $previous = $line;

            foreach (str_split($line, $pixelBytes) as $pixel) {
                $colour .= substr($pixel, 0, $pixelBytes - $sampleBytes);
                $alpha .= substr($pixel, $pixelBytes - $sampleBytes);
            }
        }

        return new self($width, $height, $colorSpace, $depth, 'FlateDecode', (string) gzcompress($colour, 9), alpha: (string) gzcompress($alpha, 9));
    }

    /**
     * Undoes a row's filter. The row decoded so far and the previous row start with one pixel of zeros, the
     * neighbours of the first pixel, so that each byte's left and upper left neighbours sit one pixel before it.
     */
    private static function unfilter(int $filter, string $line, string $previous, int $pixelBytes): string
    {
        $out = str_repeat("\0", $pixelBytes);
        $previous = $out.$previous;

        for ($index = 0, $length = \strlen($line); $index < $length; ++$index) {
            $left = \ord($out[$index]);
            $up = \ord($previous[$index + $pixelBytes]);
            $upLeft = \ord($previous[$index]);
            $predicted = match ($filter) {
                0 => 0,
                1 => $left,
                2 => $up,
                3 => intdiv($left + $up, 2),
                4 => self::paeth($left, $up, $upLeft),
                default => throw InvalidImage::because(\sprintf('The PNG row filter %d does not exist.', $filter)),
            };
            $out .= \chr((\ord($line[$index]) + $predicted) & 0xFF);
        }

        return substr($out, $pixelBytes);
    }

    private static function paeth(int $left, int $up, int $upLeft): int
    {
        $estimate = $left + $up - $upLeft;
        $toLeft = abs($estimate - $left);
        $toUp = abs($estimate - $up);
        $toUpLeft = abs($estimate - $upLeft);

        if ($toLeft <= $toUp && $toLeft <= $toUpLeft) {
            return $left;
        }

        return $toUp <= $toUpLeft ? $up : $upLeft;
    }
}
