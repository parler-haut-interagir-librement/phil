<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\DatedIdentifiable;
use Symfony\Component\Uid\UuidV7;

/**
 * `DatedIdentifiable`, mapped: the primary key `id`, a UUID v7 in a `guid` column, its text form on every database.
 * It is null until the listener, or the application, gives it a value on `prePersist`: Doctrine never writes a null.
 *
 * @phpstan-require-implements DatedIdentifiable
 */
trait DatedIdentifierEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::GUID)]
    protected ?string $id = null;

    public function datedId(): ?string
    {
        return $this->id;
    }

    public function assignDatedId(string $id): void
    {
        $this->id = new UuidV7($id)->toRfc4122();
    }

    public function datedIdTime(): ?DateTimeImmutable
    {
        return null === $this->id ? null : new UuidV7($this->id)->getDateTime();
    }
}
