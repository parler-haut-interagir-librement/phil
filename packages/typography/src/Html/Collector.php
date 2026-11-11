<?php

declare(strict_types=1);

namespace Phil\Typography\Html;

use Dom\Text;

/**
 * The parts of the run being collected: text nodes, and the text of protected elements as strings.
 *
 * @internal
 */
final class Collector
{
    /**
     * @var list<Text|string>
     */
    private array $parts = [];

    public function add(Text $text): void
    {
        $this->parts[] = $text;
    }

    public function lock(string $text): void
    {
        $this->parts[] = $text;
    }

    /**
     * The parts collected since the last call, which empties the collector.
     *
     * @return list<Text|string>
     */
    public function take(): array
    {
        $parts = $this->parts;
        $this->parts = [];

        return $parts;
    }
}
