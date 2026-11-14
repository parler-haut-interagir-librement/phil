<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Export;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * A ZIP archive written in memory, from scratch: the local headers, the central directory and its end, as the PKWARE
 * application note describes them, with no extension needed. Each file is compressed with raw deflate when zlib is
 * there, and stored otherwise; names are UTF-8 (general purpose bit 11).
 *
 * The archive stays within what the plain format can say, without ZIP64: at most 65,535 files and 4 GiB in all. A
 * person's export is far smaller; a larger one is refused rather than written broken.
 */
final class ZipFile
{
    public const int MAX_FILES = 65535;

    public const int MAX_BYTES = 0xFFFFFFFF;

    public const int MAX_NAME = 255;

    private const int UTF8_NAMES = 0x0800;

    private const int VERSION = 20;

    /**
     * @var array<string, true>
     */
    private array $names = [];

    private string $body = '';

    private string $directory = '';

    private int $count = 0;

    public function __construct(
        private readonly DateTimeImmutable $modifiedAt,
    ) {
        if ((int) $modifiedAt->format('Y') < 1980) {
            throw InvalidPrivacyValue::because('A ZIP archive cannot date a file before 1980.');
        }
    }

    public function add(string $name, string $content): void
    {
        $this->guardName($name);

        if (isset($this->names[$name])) {
            throw InvalidPrivacyValue::because(\sprintf('The archive already holds a file named "%s".', $name));
        }

        if ($this->count >= self::MAX_FILES) {
            throw InvalidPrivacyValue::because(\sprintf('A ZIP archive without ZIP64 holds at most %d files.', self::MAX_FILES));
        }

        $compressed = $content;

        if (\function_exists('gzdeflate')) {
            $deflated = gzdeflate($content, 6);

            if (false !== $deflated && \strlen($deflated) < \strlen($content)) {
                $compressed = $deflated;
            }
        }

        $method = $compressed === $content ? 0 : 8;
        $offset = \strlen($this->body);

        if ($offset + 30 + \strlen($name) + \strlen($compressed) > self::MAX_BYTES || \strlen($content) > self::MAX_BYTES) {
            throw InvalidPrivacyValue::because('A ZIP archive without ZIP64 holds at most 4 GiB.');
        }

        $time = $this->dosTime();
        $date = $this->dosDate();
        $crc = crc32($content);
        $common = pack('vvvvvVVVv', self::VERSION, self::UTF8_NAMES, $method, $time, $date, $crc, \strlen($compressed), \strlen($content), \strlen($name));

        $this->body .= pack('V', 0x04034B50).$common.pack('v', 0).$name.$compressed;
        $this->directory .= pack('Vv', 0x02014B50, self::VERSION).$common.pack('vvvvVV', 0, 0, 0, 0, 0, $offset).$name;
        $this->names[$name] = true;
        ++$this->count;
    }

    /**
     * The archive's bytes, with the files added so far.
     */
    public function bytes(): string
    {
        $offset = \strlen($this->body);

        return $this->body.$this->directory.pack('VvvvvVVv', 0x06054B50, 0, 0, $this->count, $this->count, \strlen($this->directory), $offset, 0);
    }

    private function guardName(string $name): void
    {
        $safe = '' !== $name
            && \strlen($name) <= self::MAX_NAME
            && mb_check_encoding($name, 'UTF-8')
            && !str_starts_with($name, '/')
            && !str_contains($name, '\\')
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $name)
            && !\in_array('..', explode('/', $name), true)
            && !\in_array('.', explode('/', $name), true)
            && !str_ends_with($name, '/');

        if (!$safe) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a file name an archive can hold safely: a relative UTF-8 path without "..", "." or a backslash, of at most %d bytes.', $name, self::MAX_NAME));
        }
    }

    private function dosTime(): int
    {
        return ((int) $this->modifiedAt->format('G') << 11) | ((int) $this->modifiedAt->format('i') << 5) | intdiv((int) $this->modifiedAt->format('s'), 2);
    }

    private function dosDate(): int
    {
        return (((int) $this->modifiedAt->format('Y') - 1980) << 9) | ((int) $this->modifiedAt->format('n') << 5) | (int) $this->modifiedAt->format('j');
    }
}
