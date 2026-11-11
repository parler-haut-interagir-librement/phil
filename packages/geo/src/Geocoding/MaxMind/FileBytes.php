<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\MaxMind;

use Phil\Geo\Exception\InvalidDatabase;

/**
 * The bytes of a database file, read where they are asked for: the file is opened once, on the first read, and never
 * loaded whole. It is read in blocks of 4 KB, and the blocks read are kept, at most 64 of them (256 KB), so that the
 * many small reads of a record cost one read of the file each block: when a 65th block is read, those kept are let go.
 *
 * @internal
 */
final class FileBytes
{
    private const int BLOCK = 4_096;

    private const int MAX_BLOCKS = 64;

    /** @var resource|null */
    private $handle;

    /** @var array<int, string> by block number */
    private array $blocks = [];

    private int $size = 0;

    public function __construct(
        public readonly string $path,
    ) {
    }

    public function size(): int
    {
        $this->open();

        return $this->size;
    }

    /**
     * Exactly `$length` bytes from `$offset`, or `InvalidDatabase` when the file ends before.
     */
    public function read(int $offset, int $length): string
    {
        $first = intdiv($offset, self::BLOCK);
        $at = $offset - $first * self::BLOCK;

        // Most reads are a few bytes of a block already read; a kept block is never longer than the file.
        if (isset($this->blocks[$first]) && $at + $length <= \strlen($this->blocks[$first])) {
            return substr($this->blocks[$first], $at, $length);
        }

        if ($offset + $length > $this->size()) {
            throw InvalidDatabase::corrupt($this->path, \sprintf('it ends before %d bytes from offset %d.', $length, $offset));
        }

        $last = intdiv($offset + $length - 1, self::BLOCK);
        $bytes = '';

        for ($block = $first; $block <= $last; ++$block) {
            $bytes .= $this->block($block);
        }

        return substr($bytes, $at, $length);
    }

    /**
     * The block that holds an offset, and where it starts: what a reader keeps at hand to read the bytes around it.
     *
     * @return array{int, string}
     */
    public function blockAt(int $offset): array
    {
        $block = intdiv($offset, self::BLOCK);

        return [$block * self::BLOCK, $this->block($block)];
    }

    private function block(int $block): string
    {
        if (isset($this->blocks[$block])) {
            return $this->blocks[$block];
        }

        if (\count($this->blocks) >= self::MAX_BLOCKS) {
            $this->blocks = [];
        }

        $handle = $this->open();
        fseek($handle, $block * self::BLOCK);

        return $this->blocks[$block] = (string) fread($handle, self::BLOCK);
    }

    /**
     * @return resource
     */
    private function open()
    {
        if (null !== $this->handle) {
            return $this->handle;
        }

        $handle = is_file($this->path) && is_readable($this->path) ? fopen($this->path, 'r') : false;

        if (false === $handle) {
            throw InvalidDatabase::unreadable($this->path);
        }

        $this->handle = $handle;
        $this->size = (int) filesize($this->path);

        return $handle;
    }
}
