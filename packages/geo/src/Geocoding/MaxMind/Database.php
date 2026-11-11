<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\MaxMind;

use Phil\Geo\Exception\InvalidDatabase;
use Phil\Geo\Exception\InvalidPlace;

/**
 * A MaxMind DB file — GeoLite2, GeoIP2, or any database written in that format — read on this server: an address
 * looked up never leaves it.
 *
 * The file is read as its format specification (version 2.0) describes: a binary search tree on the bits of the
 * address, then the data its leaves point to, then the metadata, found from the end of the file. It is opened on the
 * first lookup and read where each lookup leads, never loaded whole, so a 60 MB city database costs a few reads: in
 * blocks of 4 KB, of which the last 64 read (256 KB) are kept for the lookups that follow.
 */
final class Database
{
    private const string METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";

    private const int METADATA_MAX_SIZE = 131_072;

    private const int SEPARATOR = 16;

    private readonly FileBytes $bytes;

    private ?Metadata $metadata = null;

    private ?Decoder $data = null;

    private ?int $ipv4Start = null;

    public function __construct(string $path)
    {
        $this->bytes = new FileBytes($path);
    }

    public function metadata(): Metadata
    {
        if ($this->metadata instanceof Metadata) {
            return $this->metadata;
        }

        $size = $this->bytes->size();
        $tail = min($size, self::METADATA_MAX_SIZE);
        $marker = strrpos($this->bytes->read($size - $tail, $tail), self::METADATA_MARKER);

        if (false === $marker) {
            throw InvalidDatabase::corrupt($this->bytes->path, 'it has no metadata.');
        }

        $map = new Decoder($this->bytes, $size - $tail + $marker + \strlen(self::METADATA_MARKER))->decode(0)[0];

        return $this->metadata = Metadata::of(\is_array($map) ? $map : [], $this->bytes->path);
    }

    /**
     * The record of the network an address belongs to, as the database holds it; null when it holds none.
     *
     * @throws InvalidPlace    when the address is not an IP address
     * @throws InvalidDatabase when the file cannot be read as a MaxMind database
     */
    public function find(string $ip): mixed
    {
        $packed = false === filter_var($ip, \FILTER_VALIDATE_IP) ? false : inet_pton($ip);

        if (false === $packed) {
            throw InvalidPlace::ip($ip);
        }

        $metadata = $this->metadata();
        $bits = \strlen($packed) * 8;

        if (128 === $bits && 4 === $metadata->ipVersion) {
            return null;
        }

        $node = 32 === $bits && 6 === $metadata->ipVersion ? $this->ipv4Start($metadata) : 0;

        for ($i = 0; $i < $bits && $node < $metadata->nodeCount; ++$i) {
            $node = $this->record($metadata, $node, (\ord($packed[$i >> 3]) >> (7 - ($i & 7))) & 1);
        }

        if ($node === $metadata->nodeCount) {
            return null;
        }

        if ($node < $metadata->nodeCount) {
            throw InvalidDatabase::corrupt($this->bytes->path, \sprintf('its search tree does not end for %s.', $ip));
        }

        if ($node - $metadata->nodeCount < self::SEPARATOR) {
            throw InvalidDatabase::corrupt($this->bytes->path, \sprintf('the search tree points before the data for %s.', $ip));
        }

        return $this->data($metadata)->decode($node - $metadata->nodeCount - self::SEPARATOR)[0];
    }

    /**
     * Where IPv4 addresses start in a tree of IPv6 ones: after 96 zero bits, as `::a.b.c.d`.
     */
    private function ipv4Start(Metadata $metadata): int
    {
        if (null !== $this->ipv4Start) {
            return $this->ipv4Start;
        }

        $node = 0;

        for ($i = 0; $i < 96 && $node < $metadata->nodeCount; ++$i) {
            $node = $this->record($metadata, $node, 0);
        }

        return $this->ipv4Start = $node;
    }

    private function record(Metadata $metadata, int $node, int $bit): int
    {
        $size = $metadata->recordSize >> 2;
        $bytes = $this->bytes->read($node * $size, $size);

        return match ($metadata->recordSize) {
            24 => $this->number(substr($bytes, $bit * 3, 3)),
            28 => 0 === $bit
                ? ((\ord($bytes[3]) & 0xF0) << 20) | $this->number(substr($bytes, 0, 3))
                : ((\ord($bytes[3]) & 0x0F) << 24) | $this->number(substr($bytes, 4, 3)),
            default => $this->number(substr($bytes, $bit * 4, 4)),
        };
    }

    private function data(Metadata $metadata): Decoder
    {
        return $this->data ??= new Decoder($this->bytes, $metadata->nodeCount * ($metadata->recordSize >> 2) + self::SEPARATOR);
    }

    private function number(string $bytes): int
    {
        $value = 0;
        $length = \strlen($bytes);

        for ($i = 0; $i < $length; ++$i) {
            $value = ($value << 8) | \ord($bytes[$i]);
        }

        return $value;
    }
}
