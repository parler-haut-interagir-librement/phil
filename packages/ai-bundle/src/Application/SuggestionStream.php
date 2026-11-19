<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Closure;
use Generator;
use IteratorAggregate;
use LogicException;
use Override;
use Phil\AiBundle\Application\Exception\TaskFailed;

/**
 * A task's answer in pieces as the model writes them, read once: each piece is text to show at once, and the whole
 * `Suggestion` comes once the last piece is read, journaled and charged like that of `AiTasks::run()`.
 *
 * A failure while reading throws `TaskFailed`, journaled, the task given back to the quota. A stream left before its
 * end, or never read, is journaled as failed and given back too: what was shown was never a whole proposal.
 *
 * @implements IteratorAggregate<int, string>
 */
final class SuggestionStream implements IteratorAggregate
{
    private bool $started = false;

    private ?Suggestion $suggestion = null;

    /**
     * @param Generator<int, string, mixed, Suggestion> $pieces
     * @param Closure(): void                           $abandon what a stream never read does: journal and give back
     */
    public function __construct(
        private readonly Generator $pieces,
        private readonly Closure $abandon,
    ) {
    }

    public function __destruct()
    {
        if (!$this->started) {
            ($this->abandon)();
        }
    }

    /**
     * @return Generator<int, string, mixed, void>
     *
     * @throws TaskFailed when the model fails while answering
     */
    #[Override]
    public function getIterator(): Generator
    {
        if ($this->started) {
            throw new LogicException('A stream of a suggestion is read once.');
        }

        $this->started = true;

        foreach ($this->pieces as $piece) {
            yield $piece;
        }

        $this->suggestion = $this->pieces->getReturn();
    }

    /**
     * The whole suggestion, once every piece was read.
     */
    public function suggestion(): Suggestion
    {
        return $this->suggestion ?? throw new LogicException('A suggestion is whole once its stream was read to the end.');
    }
}
