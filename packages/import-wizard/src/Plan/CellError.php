<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

/**
 * Why a cell is refused: its column — null for the row as a whole —, the reason, a detail that is never a value of the
 * file (the allowed values, the column of a start date, the words of a target), and the line of the other row involved.
 */
final readonly class CellError
{
    public function __construct(
        public ?string $column,
        public Reason $reason,
        public ?string $detail = null,
        public ?int $relatedLine = null,
    ) {
    }

    /**
     * A refusal of the target, with its own words; they must not repeat a value of the row.
     */
    public static function refusedByTarget(?string $column, string $detail): self
    {
        return new self($column, Reason::RefusedByTarget, $detail);
    }

    /**
     * The English sentence of the error, with the header the column is read from.
     */
    public function message(?string $header): string
    {
        $message = ($header ?? 'Row').': '.$this->reason->message();

        if (null !== $this->relatedLine) {
            $message .= ' (line '.$this->relatedLine.')';
        }

        return null === $this->detail ? $message : $message.' — '.$this->detail;
    }
}
