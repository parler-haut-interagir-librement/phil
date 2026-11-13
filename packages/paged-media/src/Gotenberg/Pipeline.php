<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidPipeline;
use Phil\PagedMedia\Gotenberg\Stage\Operation;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\Stage;

/**
 * A list of stages, run in order, each on what the previous one produced. It holds Gotenberg's order rules, so a list
 * Gotenberg would refuse is refused when it is built:
 *
 * - `decrypt` comes first, and `render`, `extract-text` or `extract-attachments` last;
 * - a list holds at most one `remediate`, one `encrypt`, one `sign` and one `timestamp`;
 * - after `remediate`, only `encrypt`, `optimize`, `sign`, `timestamp` and a terminal stage may follow; after
 *   `encrypt`, only `optimize` and a terminal stage; after `sign` or `timestamp`, only `sign`, `timestamp` and a
 *   terminal stage;
 * - `encrypt` does not combine with `sign`, `timestamp`, or a PDF/A or Factur-X `remediate`, and `decrypt` does not
 *   combine with `sign` or `timestamp`.
 */
final readonly class Pipeline
{
    /** @var non-empty-list<Stage> */
    public array $stages;

    public function __construct(Stage ...$stages)
    {
        $stages = array_values($stages);

        if ([] === $stages) {
            throw InvalidPipeline::because('A list of stages holds one stage at least.');
        }

        $this->checkOrder($stages);
        $this->checkCombinations($stages);
        $this->stages = $stages;
    }

    /**
     * The same stages, followed by more.
     */
    public function then(Stage ...$stages): self
    {
        return new self(...$this->stages, ...$stages);
    }

    public function contains(Operation $operation): bool
    {
        return [] !== array_filter($this->stages, static fn (Stage $stage): bool => $operation === $stage->operation());
    }

    /**
     * The `stages` field: each stage's operation in `op`, its options beside it.
     */
    public function toJson(): string
    {
        return json_encode(
            array_map(static fn (Stage $stage): array => ['op' => $stage->operation()->value] + $stage->options(), $this->stages),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * The files the stages name, in order.
     *
     * @return list<InputFile>
     */
    public function files(): array
    {
        return array_merge(...array_map(static fn (Stage $stage): array => $stage->files(), $this->stages));
    }

    /**
     * @param non-empty-list<Stage> $stages
     */
    private function checkOrder(array $stages): void
    {
        $last = \count($stages) - 1;
        $seen = [];
        $constraining = [];

        foreach ($stages as $index => $stage) {
            $operation = $stage->operation();

            if (Operation::Decrypt === $operation && 0 !== $index) {
                throw InvalidPipeline::because(\sprintf('"decrypt" comes first; it is stage %d.', $index));
            }

            if ($operation->isTerminal() && $last !== $index) {
                throw InvalidPipeline::because(\sprintf('"%s" comes last; it is stage %d of %d.', $operation->value, $index, $last));
            }

            if ($operation->runsOnce() && \in_array($operation, $seen, true)) {
                throw InvalidPipeline::because(\sprintf('A list holds one "%s" at most.', $operation->value));
            }

            foreach ($constraining as $earlier) {
                $followers = $earlier->followers() ?? [];

                if (!$operation->isTerminal() && !\in_array($operation, $followers, true)) {
                    throw InvalidPipeline::because(\sprintf('"%s" cannot follow "%s": only %s, render, extract-text and extract-attachments may.', $operation->value, $earlier->value, implode(', ', array_map(static fn (Operation $follower): string => $follower->value, $followers))));
                }
            }

            $seen[] = $operation;

            if (null !== $operation->followers()) {
                $constraining[] = $operation;
            }
        }
    }

    /**
     * @param non-empty-list<Stage> $stages
     */
    private function checkCombinations(array $stages): void
    {
        $operations = array_map(static fn (Stage $stage): Operation => $stage->operation(), $stages);
        $signs = \in_array(Operation::Sign, $operations, true) || \in_array(Operation::Timestamp, $operations, true);
        $archival = [] !== array_filter($stages, static fn (Stage $stage): bool => $stage instanceof Remediate && $stage->isArchival());

        if (\in_array(Operation::Encrypt, $operations, true) && ($signs || $archival)) {
            throw InvalidPipeline::because('"encrypt" does not combine with "sign", "timestamp", or a PDF/A or Factur-X "remediate".');
        }

        if (\in_array(Operation::Decrypt, $operations, true) && $signs) {
            throw InvalidPipeline::because('"decrypt" does not combine with "sign" or "timestamp": sign the decrypted PDF in a request of its own.');
        }
    }
}
