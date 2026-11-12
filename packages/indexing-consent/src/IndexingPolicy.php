<?php

declare(strict_types=1);

namespace Phil\IndexingConsent;

use Phil\IndexingConsent\Contract\HasIndexingConsent;

/**
 * What a reader may do with any record: one that implements `HasIndexingConsent` answers for itself, and any other is
 * listed, since nobody withdrew it.
 */
final class IndexingPolicy
{
    /**
     * The attribute of the request of a page under which it keeps the `Indexing` of the record it shows, so that every
     * reader of the page — its structured data, its robots directives — reads one answer.
     */
    public const string REQUEST_ATTRIBUTE = '_indexing';

    private function __construct()
    {
    }

    public static function of(object $record): Indexing
    {
        return $record instanceof HasIndexingConsent ? $record->indexing() : Indexing::Listed;
    }

    /**
     * The strictest answer of the records a page shows; `Listed` for none.
     */
    public static function ofAll(object ...$records): Indexing
    {
        return Indexing::strictest(Indexing::Listed, ...array_map(self::of(...), array_values($records)));
    }

    public static function allows(object $record, Reader $reader): bool
    {
        return self::of($record)->allows($reader);
    }

    /**
     * The records a reader may use, in their order, their keys kept.
     *
     * @template TKey of array-key
     * @template TRecord of object
     *
     * @param iterable<TKey, TRecord> $records
     *
     * @return iterable<TKey, TRecord>
     */
    public static function filter(iterable $records, Reader $reader): iterable
    {
        foreach ($records as $key => $record) {
            if (self::allows($record, $reader)) {
                yield $key => $record;
            }
        }
    }
}
