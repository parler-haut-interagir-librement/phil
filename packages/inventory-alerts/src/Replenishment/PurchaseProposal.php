<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use DateTimeImmutable;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Exception\ProposalAlreadyDecided;
use Phil\InventoryAlerts\Internal\Check;

/**
 * A purchase order proposed to a person for one supplier: its lines by location, waiting for an approval. Only an
 * approved proposal is handed to purchasing, by the application.
 */
final readonly class PurchaseProposal
{
    /**
     * @param non-empty-list<ProposalLine> $lines
     */
    public function __construct(
        public string $supplier,
        public array $lines,
        public DateTimeImmutable $proposedAt,
        public ProposalState $state = ProposalState::Proposed,
        public ?string $decidedBy = null,
        public ?DateTimeImmutable $decidedAt = null,
    ) {
        Check::code('supplier', $supplier);
    }

    /**
     * The proposal approved by a person, with the lines kept.
     */
    public function approve(string $approvedBy, DateTimeImmutable $at): self
    {
        return $this->decide(ProposalState::Approved, $approvedBy, $at);
    }

    public function reject(string $rejectedBy, DateTimeImmutable $at): self
    {
        return $this->decide(ProposalState::Rejected, $rejectedBy, $at);
    }

    /**
     * The proposal with the quantity of a line changed by the person before approving it; a quantity of 0 drops the
     * line.
     */
    public function withQuantity(string $sku, string $location, int $quantity): self
    {
        $this->assertProposed();
        Check::units('quantity', $quantity);

        $lines = [];
        $found = false;

        foreach ($this->lines as $line) {
            if ($line->sku !== $sku || $line->location !== $location) {
                $lines[] = $line;

                continue;
            }

            $found = true;

            if ($quantity > 0) {
                $lines[] = new ProposalLine($sku, $location, $quantity, $line->position, $line->reorderPoint);
            }
        }

        if (!$found) {
            throw InvalidInput::because('line', 'must be one of the proposal');
        }

        if ([] === $lines) {
            throw InvalidInput::because('proposal', 'must keep at least one line; reject it instead');
        }

        return new self($this->supplier, $lines, $this->proposedAt);
    }

    /**
     * The lines grouped by location, in the order of the proposal.
     *
     * @return array<string, non-empty-list<ProposalLine>>
     */
    public function byLocation(): array
    {
        $groups = [];

        foreach ($this->lines as $line) {
            $groups[$line->location][] = $line;
        }

        return $groups;
    }

    public function totalUnits(): int
    {
        return array_sum(array_map(static fn (ProposalLine $line): int => $line->quantity, $this->lines));
    }

    private function decide(ProposalState $state, string $by, DateTimeImmutable $at): self
    {
        $this->assertProposed();
        Check::code('person who decides', $by);

        return new self($this->supplier, $this->lines, $this->proposedAt, $state, $by, $at);
    }

    private function assertProposed(): void
    {
        if (ProposalState::Proposed !== $this->state) {
            throw ProposalAlreadyDecided::forSupplier($this->supplier);
        }
    }
}
