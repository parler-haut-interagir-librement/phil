<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\TrashBundle\Domain\Model\TrashItem;

interface TrashItemRepository
{
    public function save(TrashItem $item): void;

    public function remove(TrashItem $item): void;

    public function get(string $id): ?TrashItem;

    /**
     * The items, most recently trashed first, of one kind when one is given.
     *
     * @return list<TrashItem>
     */
    public function list(?string $resourceKey = null, int $limit = 50, int $offset = 0): array;

    /**
     * The items whose time has come, oldest first.
     *
     * @return list<TrashItem>
     */
    public function expired(DateTimeImmutable $now, int $limit): array;
}
