<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\NewsletterArchive\Domain\Contract\ArchiveRepository;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\IssuePage;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use UnexpectedValueException;

/**
 * The archived issues in the tables of `ArchiveSchema`, through Doctrine DBAL. A save writes the issue and its tags
 * in one transaction; a page reads its issues, then their tags, in two statements.
 */
final readonly class DbalArchiveRepository implements ArchiveRepository
{
    private const array INSTANTS = ['sent_at' => Types::DATETIME_UTC_IMMUTABLE, 'rendered_at' => Types::DATETIME_UTC_IMMUTABLE, 'published_at' => Types::DATETIME_UTC_IMMUTABLE, 'link_issued_at' => Types::DATETIME_UTC_IMMUTABLE];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(ArchivedIssue $issue): void
    {
        $row = [
            'site' => $issue->site,
            'slug' => $issue->slug,
            'list_id' => $issue->listId,
            'list_key' => $issue->listKey,
            'list_name' => $issue->listName,
            'subject' => $issue->subject,
            'preheader' => $issue->preheader,
            'html' => $issue->html,
            'sent_at' => $issue->sentAt,
            'rendered_at' => $issue->renderedAt,
            'published_at' => $issue->publishedAt,
            'link_hash' => $issue->linkHash,
            'link_issued_at' => $issue->linkIssuedAt,
        ];

        $this->connection->transactional(static function (Connection $connection) use ($issue, $row): void {
            $key = ['campaign_id' => $issue->campaignId];

            if (false === $connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE campaign_id = ?', ArchiveSchema::ISSUES), [$issue->campaignId])) {
                $connection->insert(ArchiveSchema::ISSUES, [...$key, ...$row], self::INSTANTS);
            } else {
                $connection->update(ArchiveSchema::ISSUES, $row, $key, self::INSTANTS);
            }

            $connection->delete(ArchiveSchema::TAGS, $key);

            foreach ($issue->tagValues() as $tag) {
                $connection->insert(ArchiveSchema::TAGS, [...$key, 'tag' => $tag]);
            }
        });
    }

    #[Override]
    public function get(string $campaignId): ?ArchivedIssue
    {
        return $this->one($this->select()->where('i.campaign_id = :id')->setParameter('id', $campaignId));
    }

    #[Override]
    public function published(string $site, string $slug): ?ArchivedIssue
    {
        return $this->one($this->select()
            ->where('i.site = :site', 'i.slug = :slug', 'i.published_at IS NOT NULL')
            ->setParameter('site', $site)
            ->setParameter('slug', $slug));
    }

    #[Override]
    public function byLinkHash(string $linkHash): ?ArchivedIssue
    {
        return $this->one($this->select()->where('i.link_hash = :hash')->setParameter('hash', $linkHash));
    }

    #[Override]
    public function slugTaken(string $site, string $slug): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE site = ? AND slug = ?', ArchiveSchema::ISSUES), [$site, $slug]);
    }

    #[Override]
    public function page(ArchiveFilter $filter): IssuePage
    {
        $count = $this->filtered($this->connection->createQueryBuilder()->select('COUNT(*)')->from(ArchiveSchema::ISSUES, 'i'), $filter);
        $total = $this->integer($count->executeQuery()->fetchOne());
        $rows = [];

        if ($total > $filter->offset()) {
            $rows = $this->filtered($this->select(), $filter)
                ->orderBy('i.sent_at', 'DESC')
                ->addOrderBy('i.campaign_id', 'DESC')
                ->setFirstResult($filter->offset())
                ->setMaxResults($filter->pageSize)
                ->executeQuery()
                ->fetchAllAssociative();
        }

        return new IssuePage($this->hydrate($rows), $total, $filter);
    }

    #[Override]
    public function lists(string $site): array
    {
        $lists = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT DISTINCT list_key, list_name FROM %s WHERE site = ? AND published_at IS NOT NULL AND list_key <> ? ORDER BY list_key, list_name', ArchiveSchema::ISSUES), [$site, '']) as $row) {
            $lists[self::string($row['list_key'])] ??= self::string($row['list_name']);
        }

        return $lists;
    }

    #[Override]
    public function tags(string $site): array
    {
        return array_map(self::string(...), $this->connection->fetchFirstColumn(\sprintf(
            'SELECT DISTINCT t.tag FROM %s t INNER JOIN %s i ON i.campaign_id = t.campaign_id WHERE i.site = ? AND i.published_at IS NOT NULL ORDER BY t.tag',
            ArchiveSchema::TAGS,
            ArchiveSchema::ISSUES,
        ), [$site]));
    }

    private function select(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('i.*')->from(ArchiveSchema::ISSUES, 'i');
    }

    private function filtered(QueryBuilder $query, ArchiveFilter $filter): QueryBuilder
    {
        $query->where('i.site = :site', 'i.published_at IS NOT NULL')->setParameter('site', $filter->site);

        if (null !== $filter->listKey) {
            $query->andWhere('i.list_key = :list')->setParameter('list', $filter->listKey);
        }

        if ($filter->tag instanceof Tag) {
            $query->andWhere(\sprintf('EXISTS (SELECT 1 FROM %s t WHERE t.campaign_id = i.campaign_id AND t.tag = :tag)', ArchiveSchema::TAGS))
                ->setParameter('tag', $filter->tag->value);
        }

        return $query;
    }

    private function one(QueryBuilder $query): ?ArchivedIssue
    {
        $row = $query->executeQuery()->fetchAssociative();

        return false === $row ? null : $this->hydrate([$row])[0];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<ArchivedIssue>
     */
    private function hydrate(array $rows): array
    {
        if ([] === $rows) {
            return [];
        }

        $ids = array_map(static fn (array $row): string => self::string($row['campaign_id']), $rows);
        $tags = [];

        foreach ($this->connection->fetchAllAssociative(
            \sprintf('SELECT campaign_id, tag FROM %s WHERE campaign_id IN (?)', ArchiveSchema::TAGS),
            [$ids],
            [ArrayParameterType::STRING],
        ) as $row) {
            $tags[self::string($row['campaign_id'])][] = Tag::of(self::string($row['tag']));
        }

        return array_map(fn (array $row): ArchivedIssue => ArchivedIssue::restore(
            new ArchivedIssue(
                self::string($row['campaign_id']),
                self::string($row['site']),
                self::string($row['slug']),
                self::string($row['list_id']),
                self::string($row['list_key']),
                self::string($row['list_name']),
                self::string($row['subject']),
                self::string($row['preheader']),
                self::string($row['html']),
                $this->instant($row['sent_at']) ?? throw new UnexpectedValueException('An archived issue has a date it was sent.'),
                $this->instant($row['rendered_at']) ?? throw new UnexpectedValueException('An archived issue has a date it was rendered.'),
                $tags[self::string($row['campaign_id'])] ?? [],
            ),
            $this->instant($row['published_at']),
            null === $row['link_hash'] ? null : self::string($row['link_hash']),
            $this->instant($row['link_issued_at']),
        ), $rows);
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        $instant = Type::getType(Types::DATETIME_UTC_IMMUTABLE)->convertToPHPValue($value, $this->connection->getDatabasePlatform());

        return $instant instanceof DateTimeImmutable ? $instant : null;
    }

    private function integer(mixed $value): int
    {
        if (\is_int($value) || (\is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        throw new UnexpectedValueException('A count of the archive is a whole number.');
    }

    private static function string(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A column of the archive holds a string.');
        }

        return $value;
    }
}
