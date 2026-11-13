<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\CommentsBundle\Domain\Status\ThreadStatus;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns the application's migrations create, as the mapping declares them on the entities.
 */
#[CoversClass(Thread::class)]
#[CoversClass(Comment::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        $thread = Thread::open('t1', ResourceReference::of('page', '1'), Anchor::of(null, null, 0, 'x'), ResourceReference::of('user', 'ada'), new DateTimeImmutable());
        self::assertSame('t1', Comment::write('c1', $thread, ResourceReference::of('user', 'ada'), 'x', new DateTimeImmutable())->thread);

        self::assertSame([
            'table' => 'phil_comment_thread',
            'id' => ['id'],
            'indexes' => [['subject']],
            'columns' => [
                'block' => ['block', 'string', 64, true, null],
                'field' => ['field', 'string', 1039, true, null],
                'start' => ['start_offset', 'integer', null, true, null],
                'quote' => ['quote', 'text', null, true, null],
                'prefix' => ['prefix', 'string', 255, false, null],
                'suffix' => ['suffix', 'string', 255, false, null],
                'detached' => ['detached', 'boolean', null, false, null],
                'status' => ['status', 'string', 16, false, ThreadStatus::class],
                'suggestionState' => ['suggestion_state', 'string', 16, true, SuggestionState::class],
                'resolvedBy' => ['resolved_by', 'string', 320, true, null],
                'resolvedAt' => ['resolved_at', 'datetime_immutable', null, true, null],
                'id' => ['id', 'string', 36, false, null],
                'subject' => ['subject', 'string', 320, false, null],
                'author' => ['author', 'string', 320, false, null],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
                'suggestion' => ['suggestion', 'text', null, true, null],
            ],
        ], $this->describe($entityManager, Thread::class));

        self::assertSame([
            'table' => 'phil_comment',
            'id' => ['id'],
            'indexes' => [['thread']],
            'columns' => [
                'body' => ['body', 'text', null, false, null],
                'editedAt' => ['edited_at', 'datetime_immutable', null, true, null],
                'id' => ['id', 'string', 36, false, null],
                'thread' => ['thread', 'string', 36, false, null],
                'author' => ['author', 'string', 320, false, null],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
            ],
        ], $this->describe($entityManager, Comment::class));
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
            'columns' => $columns,
        ];
    }
}
