<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Shifts\Format\SwapFormat;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;
use Phil\ShiftsBundle\Domain\Model\StoredSwap;
use Phil\ShiftsBundle\Domain\Repository\SwapRepository;

/**
 * The offers of the swap board through Doctrine DBAL, each as the fields of the format, with its shift, volunteers,
 * status and revision apart. A write is conditional on the revision read.
 */
final readonly class DbalSwapRepository implements SwapRepository
{
    private const int MAX = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(SwapOffer $offer): void
    {
        $this->connection->insert(ShiftsTables::SWAPS, [
            'id' => $offer->id,
            'shift_id' => $offer->shiftId,
            'offered_by' => $offer->offeredBy,
            'created_at' => $offer->createdAt,
            'revision' => 1,
            ...$this->columns($offer),
        ], ['created_at' => Types::DATETIME_UTC_IMMUTABLE, 'revision' => Types::INTEGER]);
    }

    #[Override]
    public function update(SwapOffer $offer, int $revision): bool
    {
        return 1 === $this->connection->update(
            ShiftsTables::SWAPS,
            ['revision' => $revision + 1, ...$this->columns($offer)],
            ['id' => $offer->id, 'revision' => $revision],
            ['revision' => Types::INTEGER],
        );
    }

    #[Override]
    public function find(string $id): ?StoredSwap
    {
        $row = $this->connection->fetchAssociative('SELECT state, revision FROM '.ShiftsTables::SWAPS.' WHERE id = ?', [$id]);

        return false === $row ? null : new StoredSwap(SwapFormat::fromArray(Json::decode($row['state'])), Json::int($row['revision']));
    }

    #[Override]
    public function active(int $limit = 200): array
    {
        return $this->offers(
            'SELECT state FROM '.ShiftsTables::SWAPS.' WHERE status IN (?, ?) ORDER BY created_at, id LIMIT ?',
            [SwapStatus::Open->value, SwapStatus::Pending->value, max(1, min($limit, self::MAX))],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function ofVolunteer(string $volunteer): array
    {
        return $this->offers('SELECT state FROM '.ShiftsTables::SWAPS.' WHERE offered_by = ? OR taken_by = ? ORDER BY created_at, id LIMIT '.self::MAX, [$volunteer, $volunteer]);
    }

    #[Override]
    public function eraseVolunteer(string $volunteer): void
    {
        $this->connection->executeStatement('DELETE FROM '.ShiftsTables::SWAPS.' WHERE offered_by = ? OR taken_by = ?', [$volunteer, $volunteer]);
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(SwapOffer $offer): array
    {
        return [
            'taken_by' => $offer->takenBy,
            'status' => $offer->status->value,
            'state' => Json::encode(SwapFormat::toArray($offer)),
        ];
    }

    /**
     * @param list<mixed>         $parameters
     * @param list<ParameterType> $types
     *
     * @return list<SwapOffer>
     */
    private function offers(string $sql, array $parameters, array $types = []): array
    {
        return array_map(static fn (mixed $state): SwapOffer => SwapFormat::fromArray(Json::decode($state)), $this->connection->fetchFirstColumn($sql, $parameters, $types));
    }
}
