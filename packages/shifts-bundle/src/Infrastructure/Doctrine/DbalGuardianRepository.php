<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;
use Phil\ShiftsBundle\Domain\Repository\GuardianRepository;

/**
 * The contacts of the guardians through Doctrine DBAL, one row per volunteer, replaced in one transaction.
 */
final readonly class DbalGuardianRepository implements GuardianRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(GuardianContact $contact): void
    {
        $this->connection->transactional(function () use ($contact): void {
            $this->connection->delete(ShiftsTables::GUARDIANS, ['volunteer' => $contact->volunteer]);
            $this->connection->insert(ShiftsTables::GUARDIANS, [
                'volunteer' => $contact->volunteer,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'recorded_by' => $contact->recordedBy,
                'consented_at' => $contact->consentedAt,
                'consent_recorded_by' => $contact->consentRecordedBy,
            ], ['consented_at' => Types::DATETIME_UTC_IMMUTABLE]);
        });
    }

    #[Override]
    public function find(string $volunteer): ?GuardianContact
    {
        $row = $this->connection->fetchAssociative(
            'SELECT name, phone, email, recorded_by, consented_at, consent_recorded_by FROM '.ShiftsTables::GUARDIANS.' WHERE volunteer = ?',
            [$volunteer],
        );

        if (false === $row) {
            return null;
        }

        return new GuardianContact(
            $volunteer,
            Json::string($row['name']),
            $this->optional($row['phone']),
            $this->optional($row['email']),
            Json::string($row['recorded_by']),
            Json::instant($this->connection, $row['consented_at']),
            $this->optional($row['consent_recorded_by']),
        );
    }

    #[Override]
    public function remove(string $volunteer): void
    {
        $this->connection->delete(ShiftsTables::GUARDIANS, ['volunteer' => $volunteer]);
    }

    private function optional(mixed $value): ?string
    {
        return null === $value ? null : Json::string($value);
    }
}
