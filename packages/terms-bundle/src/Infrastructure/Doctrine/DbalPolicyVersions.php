<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\TermsBundle\Domain\Contract\PolicyVersionRepository;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * The versions in `phil_terms_version`, through DBAL: inserted once, the unique index on the policy and the number
 * refusing a second version of the same number — the race of two publications ends in one refusal. A row that no
 * longer reads as a version is left out.
 */
final readonly class DbalPolicyVersions implements PolicyVersionRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(PolicyVersion $version): void
    {
        try {
            $this->connection->insert(TermsTables::VERSIONS, [
                'policy' => $version->policy,
                'number' => $version->number,
                'format' => $version->format->value,
                'texts' => json_encode($version->texts, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
                'published_at' => $version->publishedAt,
                'effective_at' => $version->effectiveAt,
                'material' => $version->material,
                'licence' => $version->licence,
            ], [
                'published_at' => Types::DATETIME_UTC_IMMUTABLE,
                'effective_at' => Types::DATETIME_UTC_IMMUTABLE,
                'material' => Types::BOOLEAN,
            ]);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw new InvalidTerms(\sprintf('Policy "%s" already has a version %d.', $version->policy, $version->number), 'version_taken', ['%policy%' => $version->policy, '%version%' => (string) $version->number], previous: $uniqueConstraintViolationException);
        }
    }

    #[Override]
    public function all(string $policy): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT policy, number, format, texts, published_at, effective_at, material, licence FROM '.TermsTables::VERSIONS.' WHERE policy = ? ORDER BY number DESC',
            [$policy],
        );

        $read = new RowReader($this->connection->getDatabasePlatform());

        return array_values(array_filter(array_map(fn (array $row): ?object => $this->hydrate($read, $row), $rows)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(RowReader $read, array $row): ?PolicyVersion
    {
        $policy = $read->text($row, 'policy');
        $number = $read->number($row, 'number');
        $format = TextFormat::tryFrom($read->text($row, 'format') ?? '');
        $published = $read->moment($row, 'published_at');
        $effective = $read->moment($row, 'effective_at');

        if (\in_array(null, [$policy, $number, $format, $published, $effective], true)) {
            return null;
        }

        try {
            $texts = json_decode($read->text($row, 'texts') ?? '', true, flags: \JSON_THROW_ON_ERROR);

            return new PolicyVersion($policy, $number, \is_array($texts) ? $texts : [], $format, $published, $effective, $read->flag($row, 'material'), $read->text($row, 'licence'));
        } catch (InvalidTerms|JsonException) {
            return null;
        }
    }
}
