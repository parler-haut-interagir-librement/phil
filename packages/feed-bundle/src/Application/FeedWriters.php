<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application;

use Phil\FeedBundle\Application\Contract\FeedWriter;
use Phil\FeedBundle\Domain\FeedFormat;

/**
 * The writers of the formats the application serves, by format: a format without a writer is not served.
 */
final readonly class FeedWriters
{
    /** @var array<string, FeedWriter> */
    private array $byFormat;

    /**
     * @param iterable<FeedWriter> $writers the last writer of a format wins
     */
    public function __construct(iterable $writers)
    {
        $byFormat = [];

        foreach ($writers as $writer) {
            $byFormat[$writer->format()->value] = $writer;
        }

        $this->byFormat = $byFormat;
    }

    public function for(FeedFormat $format): ?FeedWriter
    {
        return $this->byFormat[$format->value] ?? null;
    }

    /**
     * @return list<FeedFormat> in the order of the enumeration
     */
    public function formats(): array
    {
        return array_values(array_filter(FeedFormat::cases(), fn (FeedFormat $format): bool => isset($this->byFormat[$format->value])));
    }
}
