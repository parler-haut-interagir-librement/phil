<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The lists in the table `phil_newsletter_list`.
 */
final readonly class DbalMailingListRepository implements MailingListRepository
{
    private const string COLUMNS = 'id, site, list_key, name, double_opt_in, sender_profile, created_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(MailingList $list): void
    {
        Sql::upsert($this->connection, NewsletterSchema::LISTS, [
            'id' => $list->id,
            'site' => $list->site->key,
            'list_key' => $list->key,
            'name' => $list->name,
            'double_opt_in' => $list->doubleOptIn ? 1 : 0,
            'sender_profile' => $list->senderProfile,
            'created_at' => $list->createdAt->getTimestamp(),
        ], ['id']);
    }

    #[Override]
    public function get(string $id): ?MailingList
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE id = ?', self::COLUMNS, NewsletterSchema::LISTS), [$id]);

        return false === $row ? null : $this->list($row);
    }

    #[Override]
    public function byKey(Site $site, string $key): ?MailingList
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE site = ? AND list_key = ?', self::COLUMNS, NewsletterSchema::LISTS), [$site->key, $key]);

        return false === $row ? null : $this->list($row);
    }

    #[Override]
    public function ofSite(Site $site): array
    {
        return array_map($this->list(...), $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE site = ? ORDER BY list_key', self::COLUMNS, NewsletterSchema::LISTS), [$site->key]));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function list(array $row): MailingList
    {
        return new MailingList(
            Sql::string($row['id']),
            Site::of(Sql::string($row['site'])),
            Sql::string($row['list_key']),
            Sql::string($row['name']),
            1 === Sql::integer($row['double_opt_in']),
            Sql::string($row['sender_profile']),
            Sql::instant($row['created_at']),
        );
    }
}
