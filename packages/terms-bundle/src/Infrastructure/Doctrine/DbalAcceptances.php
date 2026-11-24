<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;

/**
 * The acceptances in `phil_terms_acceptance`, through DBAL: one insert each, never an update; a delete only for the
 * erasure of a member.
 */
final readonly class DbalAcceptances implements AcceptanceRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function append(Acceptance $acceptance): void
    {
        $this->connection->insert(TermsTables::ACCEPTANCES, [
            'member' => $acceptance->member,
            'policy' => $acceptance->policy,
            'version' => $acceptance->version,
            'accepted_at' => $acceptance->acceptedAt,
            'channel' => $acceptance->channel->value,
            'network' => $acceptance->network,
            'locale' => $acceptance->locale,
            'text_sha256' => $acceptance->textSha256,
        ], ['accepted_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function compliant(string $policy, array $members, int $minimum, ?DateTimeImmutable $since = null): array
    {
        $sql = 'SELECT DISTINCT member FROM '.TermsTables::ACCEPTANCES.' WHERE policy = ? AND version >= ? AND member IN (?)';
        $parameters = [$policy, $minimum, $members];
        $types = [ParameterType::STRING, ParameterType::INTEGER, ArrayParameterType::STRING];

        if ($since instanceof DateTimeImmutable) {
            $sql .= ' AND accepted_at >= ?';
            $parameters[] = $since;
            $types[] = Types::DATETIME_UTC_IMMUTABLE;
        }

        return array_values(array_filter($this->connection->fetchFirstColumn($sql, $parameters, $types), is_string(...)));
    }

    #[Override]
    public function ofMember(string $member): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT member, policy, version, accepted_at, channel, network, locale, text_sha256 FROM '.TermsTables::ACCEPTANCES.' WHERE member = ? ORDER BY id',
            [$member],
        );

        $read = new RowReader($this->connection->getDatabasePlatform());

        return array_values(array_filter(array_map(fn (array $row): ?object => $this->hydrate($read, $row), $rows)));
    }

    #[Override]
    public function erase(string $member): int
    {
        return (int) $this->connection->delete(TermsTables::ACCEPTANCES, ['member' => $member]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(RowReader $read, array $row): ?Acceptance
    {
        $member = $read->text($row, 'member');
        $policy = $read->text($row, 'policy');
        $version = $read->number($row, 'version');
        $at = $read->moment($row, 'accepted_at');
        $channel = AcceptanceChannel::tryFrom($read->text($row, 'channel') ?? '');

        if (\in_array(null, [$member, $policy, $version, $at, $channel], true)) {
            return null;
        }

        try {
            return new Acceptance($member, $policy, $version, $at, $channel, $read->text($row, 'network'), $read->text($row, 'locale'), $read->text($row, 'text_sha256'));
        } catch (InvalidTerms) {
            return null;
        }
    }
}
