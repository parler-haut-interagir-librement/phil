<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FollowBundle\Domain\Contract\FollowerVisibilityRepository;

/**
 * Whether each member hides their followers, one row per member who chose.
 */
final readonly class DbalFollowerVisibilityRepository implements FollowerVisibilityRepository
{
    private const string TABLE = FollowTables::VISIBILITY;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function hidden(string $member): ?bool
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT followers_hidden FROM %s WHERE member = ?', self::TABLE), [$member]);

        return false === $row ? null : new Row($row, $this->connection)->bool('followers_hidden');
    }

    #[Override]
    public function choose(string $member, bool $hidden): void
    {
        if (null !== $this->hidden($member)) {
            $this->connection->update(self::TABLE, ['followers_hidden' => $hidden], ['member' => $member], ['followers_hidden' => Types::BOOLEAN]);

            return;
        }

        $this->connection->insert(self::TABLE, ['member' => $member, 'followers_hidden' => $hidden], ['followers_hidden' => Types::BOOLEAN]);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->connection->delete(self::TABLE, ['member' => $member]);
    }
}
