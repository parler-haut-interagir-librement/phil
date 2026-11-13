<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns the application's migrations create, as the mapping declares them on the entities.
 */
#[CoversClass(Poll::class)]
#[CoversClass(Vote::class)]
#[CoversClass(Participation::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $poll = Poll::create('p1', 'Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], PollRules::single(['a', 'b']), 'choices', true, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable());
        self::assertSame('p1', Vote::named('v1', $poll->id, ResourceReference::of('user', 'ada'), Ballot::of(['a']), new DateTimeImmutable())->poll);
        self::assertSame('p1', Vote::anonymous('v2', $poll->id, 'hash', Ballot::of(['a']))->poll);
        self::assertSame('p1', Participation::of($poll->id, ResourceReference::of('user', 'ada'))->poll);

        self::assertSame([Poll::TABLE, Vote::TABLE, Participation::TABLE], ['phil_poll', 'phil_poll_vote', 'phil_poll_participation']);
        self::assertSame([
            'table' => 'phil_poll',
            'id' => ['id'],
            'indexes' => [['subject']],
            'unique' => [],
            'columns' => [
                'seats' => ['seats', 'smallint', null, false, null],
                'revision' => ['revision', 'bigint', null, false, null],
                'opensAt' => ['opens_at', 'datetime_immutable', null, true, null],
                'closesAt' => ['closes_at', 'datetime_immutable', null, true, null],
                'closedAt' => ['closed_at', 'datetime_immutable', null, true, null],
                'id' => ['id', 'string', 36, false, null],
                'question' => ['question', 'text', null, false, null],
                'options' => ['options', 'json', null, false, null],
                'kind' => ['kind', 'string', 16, false, BallotKind::class],
                'minChoices' => ['min_choices', 'smallint', null, false, null],
                'maxChoices' => ['max_choices', 'smallint', null, false, null],
                'abstention' => ['abstention', 'boolean', null, false, null],
                'counting' => ['counting', 'string', 64, false, null],
                'anonymous' => ['anonymous', 'boolean', null, false, null],
                'results' => ['results', 'string', 16, false, ResultsVisibility::class],
                'subject' => ['subject', 'string', 320, true, null],
                'createdBy' => ['created_by', 'string', 320, false, null],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
            ],
        ], $this->describe($entityManager, Poll::class));

        self::assertSame([
            'table' => 'phil_poll_vote',
            'id' => ['id'],
            'indexes' => [],
            'unique' => ['phil_poll_vote_voter' => ['poll', 'voter'], 'phil_poll_vote_receipt' => ['receipt']],
            'columns' => [
                'id' => ['id', 'string', 36, false, null],
                'poll' => ['poll', 'string', 36, false, null],
                'voter' => ['voter', 'string', 320, true, null],
                'receipt' => ['receipt', 'string', 64, true, null],
                'choices' => ['choices', 'json', null, false, null],
                'castAt' => ['cast_at', 'datetime_immutable', null, true, null],
            ],
        ], $this->describe($entityManager, Vote::class));

        self::assertSame([
            'table' => 'phil_poll_participation',
            'id' => ['poll', 'voter'],
            'indexes' => [],
            'unique' => [],
            'columns' => [
                'poll' => ['poll', 'string', 36, false, null],
                'voter' => ['voter', 'string', 320, false, null],
            ],
        ], $this->describe($entityManager, Participation::class));
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function describe(EntityManager $entityManager, string $class): array
    {
        $metadata = $entityManager->getClassMetadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $name => $mapping) {
            $columns[$name] = [$mapping->columnName, $mapping->type, $mapping->length, true === $mapping->nullable, $mapping->enumType];
        }

        return [
            'table' => $metadata->getTableName(),
            'id' => $metadata->getIdentifierFieldNames(),
            'indexes' => array_column($metadata->table['indexes'] ?? [], 'columns'),
            'unique' => array_map(static fn (mixed $constraint): mixed => \is_array($constraint) ? $constraint['columns'] ?? null : null, $metadata->table['uniqueConstraints'] ?? []),
            'columns' => $columns,
        ];
    }
}
