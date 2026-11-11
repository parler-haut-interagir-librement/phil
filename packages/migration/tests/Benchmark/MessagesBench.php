<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Benchmark;

use Generator;
use Phil\Migration\Messages;

/**
 * Release notes are cut into chat messages after every migration run; a long release with a generous transport limit
 * makes each message hold many pieces, and each piece re-measures the message built so far.
 *
 * @Groups({"migration"})
 */
final readonly class MessagesBench
{
    /**
     * @var list<string>
     */
    private array $lines;

    public function __construct()
    {
        $words = explode(' ', 'la migration ajoute une colonne à la table des événements pour garder le fuseau horaire de chaque série récurrente');
        $lines = [];

        for ($index = 0; $index < 500; ++$index) {
            $lines[] = '- '.implode(' ', \array_slice([...$words, ...$words, ...$words, ...$words], $index % 7, 20 + ($index % 5) * 15));
        }

        $this->lines = $lines;
    }

    /**
     * @ParamProviders({"lineCounts", "limits"})
     *
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @param array{lines: int, maxLength: int} $params
     */
    public function benchSplit(array $params): void
    {
        Messages::split(\array_slice($this->lines, 0, $params['lines']), 'Release 2.4{counter}', 'Release 2.4, continued{counter}', $params['maxLength']);
    }

    /**
     * @return Generator<string, array{lines: int}>
     */
    public function lineCounts(): Generator
    {
        yield '20 lines' => ['lines' => 20];
        yield '100 lines' => ['lines' => 100];
        yield '500 lines' => ['lines' => 500];
    }

    /**
     * @return Generator<string, array{maxLength: int}>
     */
    public function limits(): Generator
    {
        yield '500 chars' => ['maxLength' => 500];
        yield '2000 chars' => ['maxLength' => 2_000];
        yield '40000 chars' => ['maxLength' => 40_000];
    }
}
