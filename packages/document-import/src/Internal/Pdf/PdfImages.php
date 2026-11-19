<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;

/**
 * The images of a PDF taken out as media: a JPEG as it is, 8-bit grey or RGB samples written as a PNG file; any
 * other image reported. An image drawn again — a logo on every page — is taken once; an image smaller than 8 pixels,
 * a rule or a dot, is left alone.
 *
 * @internal
 */
final class PdfImages
{
    private const int SMALLEST = 8;

    /** @var array<string, string> the hashes of the images met, against an image drawn twice */
    private array $seen = [];

    private int $count = 0;

    public function __construct(
        private readonly PdfDocument $document,
        private readonly Builder $out,
    ) {
    }

    /**
     * @throws LimitExceeded
     */
    public function image(PdfStream $image): ?Media
    {
        $dictionary = $image->dictionary;
        $width = $this->document->resolve($dictionary->get('Width'));
        $height = $this->document->resolve($dictionary->get('Height'));

        if (!\is_int($width) || !\is_int($height) || $width < self::SMALLEST || $height < self::SMALLEST) {
            return null;
        }

        $key = hash('sha256', $image->raw);

        if (isset($this->seen[$key])) {
            return null;
        }

        $this->seen[$key] = $key;
        [$bytes, $stopped] = $this->document->decode($image);

        $media = match (true) {
            'DCTDecode' === $stopped => new Media('image-'.++$this->count.'.jpg', $bytes),
            null === $stopped => $this->png($bytes, $width, $height, $dictionary),
            default => null,
        };

        if (!$media instanceof Media) {
            $this->out->report->add(Omitted::Image, null === $stopped ? 'pdf colour space' : 'pdf '.$stopped);
        }

        return $media;
    }

    /**
     * @throws LimitExceeded
     */
    private function png(string $samples, int $width, int $height, PdfDictionary $dictionary): ?Media
    {
        $space = $this->document->resolve($dictionary->get('ColorSpace'));
        $channels = match ($space instanceof PdfName ? $space->name : null) {
            'DeviceGray', 'G' => 1,
            'DeviceRGB', 'RGB' => 3,
            default => null,
        };

        if (null === $channels || 8 !== $this->document->resolve($dictionary->get('BitsPerComponent')) || \strlen($samples) < $width * $height * $channels) {
            return null;
        }

        $rows = '';
        $row = $width * $channels;

        for ($line = 0; $line < $height; ++$line) {
            $rows .= "\0".substr($samples, $line * $row, $row);
        }

        $png = "\x89PNG\r\n\x1A\n"
            .$this->chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 3 === $channels ? 2 : 0, 0, 0, 0))
            .$this->chunk('IDAT', (string) gzcompress($rows))
            .$this->chunk('IEND', '');

        return new Media('image-'.++$this->count.'.png', $png);
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
