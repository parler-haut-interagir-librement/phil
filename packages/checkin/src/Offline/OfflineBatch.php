<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\InvalidOfflineBatch;

/**
 * Reads the journal a device sends when it is back online: a list of at most a limit of scans, each read by
 * {@see OfflineScan::fromArray()}. The whole batch is refused at its first malformed scan, before anything is
 * recorded.
 */
final readonly class OfflineBatch
{
    public const int LIMIT = 500;

    /**
     * @return list<OfflineScan>
     *
     * @throws InvalidOfflineBatch
     */
    public static function read(mixed $scans, int $limit = self::LIMIT): array
    {
        if (!\is_array($scans) || !array_is_list($scans)) {
            throw InvalidOfflineBatch::because('A batch is a list of scans.');
        }

        if (\count($scans) > $limit) {
            throw InvalidOfflineBatch::because(\sprintf('A batch holds at most %d scans.', $limit));
        }

        $read = [];

        foreach ($scans as $index => $scan) {
            if (!\is_array($scan)) {
                throw InvalidOfflineBatch::at($index, 'a scan is an object.');
            }

            try {
                $read[] = OfflineScan::fromArray($scan);
            } catch (InvalidCheckin $invalidCheckin) {
                throw InvalidOfflineBatch::at($index, $invalidCheckin->getMessage());
            }
        }

        return $read;
    }
}
