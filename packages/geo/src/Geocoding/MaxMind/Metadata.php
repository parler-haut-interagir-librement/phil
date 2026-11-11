<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\MaxMind;

use DateTimeImmutable;
use Phil\Geo\Exception\InvalidDatabase;

/**
 * What a MaxMind database says of itself: the shape of its search tree, the addresses it holds, what it is and when
 * it was built.
 */
final readonly class Metadata
{
    /**
     * @param list<string> $languages
     */
    private function __construct(
        public int $nodeCount,
        public int $recordSize,
        public int $ipVersion,
        public string $databaseType,
        public array $languages,
        public DateTimeImmutable $builtAt,
    ) {
    }

    /**
     * @param array<mixed> $map the metadata map of the file
     */
    public static function of(array $map, string $path): self
    {
        $major = $map['binary_format_major_version'] ?? null;
        $nodeCount = $map['node_count'] ?? null;
        $recordSize = $map['record_size'] ?? null;
        $ipVersion = $map['ip_version'] ?? null;
        $type = $map['database_type'] ?? null;
        $epoch = $map['build_epoch'] ?? null;

        $reason = match (true) {
            2 !== $major => 'its format is not version 2.',
            !\is_int($nodeCount) || $nodeCount < 1 => 'its search tree has no node.',
            !\in_array($recordSize, [24, 28, 32], true) => 'its records are not 24, 28 or 32 bits.',
            !\in_array($ipVersion, [4, 6], true) => 'it holds neither IPv4 nor IPv6 addresses.',
            !\is_string($type) || '' === $type => 'it does not name its type.',
            !\is_int($epoch) || $epoch < 0 => 'it does not say when it was built.',
            default => null,
        };

        if (null !== $reason) {
            throw InvalidDatabase::corrupt($path, $reason);
        }

        $languages = array_values(array_filter(\is_array($map['languages'] ?? null) ? $map['languages'] : [], \is_string(...)));

        return new self($nodeCount, $recordSize, $ipVersion, $type, $languages, new DateTimeImmutable('@'.$epoch));
    }
}
