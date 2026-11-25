<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Infrastructure\Outbox;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Infrastructure\Outbox\IndexEntity;
use Phil\SearchBundle\Infrastructure\Outbox\OutboxIndexingListener;
use Phil\SearchBundle\Infrastructure\Outbox\RemoveDocument;
use Phil\SearchBundle\Infrastructure\Outbox\SearchOutboxCodec;
use Phil\SearchBundle\Tests\Unit\Fixtures\Article;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchOutboxCodec::class)]
#[CoversClass(IndexEntity::class)]
#[CoversClass(RemoveDocument::class)]
#[CoversClass(OutboxIndexingListener::class)]
final class SearchOutboxCodecTest extends TestCase
{
    public function testItRebuildsBothTypes(): void
    {
        $codec = $this->codec(mapped: true);

        self::assertSame([IndexEntity::TYPE, RemoveDocument::TYPE], $codec->types());
        self::assertSame(['phil_search.index_entity', 'phil_search.remove_document'], [IndexEntity::TYPE, RemoveDocument::TYPE]);
        self::assertEquals(
            new IndexEntity(Article::class, ['id' => 'a-1', 'version' => 3]),
            $codec->decode(...$this->record(IndexEntity::TYPE, ['class' => Article::class, 'id_id' => 'a-1', 'id_version' => 3, 'other' => 'x', 'id_flag' => true])),
        );
        self::assertEquals(new RemoveDocument('pages', 'about'), $codec->decode(...$this->record(RemoveDocument::TYPE, ['index' => 'pages', 'document' => 'about'])));
    }

    public function testARecordOfAClassNoLongerMappedIsSkipped(): void
    {
        foreach ([[Article::class, false], ['App\\Gone', true]] as [$class, $mapped]) {
            try {
                $this->codec($mapped)->decode(...$this->record(IndexEntity::TYPE, ['class' => $class, 'id_id' => 'a-1']));
                self::fail($class);
            } catch (ForgottenRecord $forgotten) {
                self::assertSame(\sprintf('No entity manager maps %s any more.', $class), $forgotten->getMessage());
            }
        }
    }

    public function testTheNameOfAClassNoLongerMappedIsCutToOneHundredAndTwentyColumns(): void
    {
        $class = 'App\\'.str_repeat('a', 200);

        try {
            $this->codec(true)->decode(...$this->record(IndexEntity::TYPE, ['class' => $class, 'id_id' => 'a-1']));
            self::fail('A class that does not exist was decoded.');
        } catch (ForgottenRecord $forgottenRecord) {
            self::assertSame('No entity manager maps App\\'.str_repeat('a', 115).'… any more.', $forgottenRecord->getMessage());
        }
    }

    public function testARemovalNamesAnIndex(): void
    {
        $this->expectException(InvalidSearch::class);

        $this->codec(true)->decode(...$this->record(RemoveDocument::TYPE, ['index' => 'Pages', 'document' => 'about']));
    }

    public function testTheRecordsOfOneEntityShareTheirKey(): void
    {
        $key = OutboxIndexingListener::orderingKey(Article::class, ['id' => 'a-1']);

        self::assertSame('phil_search:2bef86b7e46699612952cbd721056cd5', $key, 'the hash of the class, a NUL byte and the identifier as JSON');
        self::assertMatchesRegularExpression('/^phil_search:[0-9a-f]{32}$/D', $key);
        self::assertMatchesRegularExpression(OutboxRecord::KEY_PATTERN, $key);
        self::assertSame($key, OutboxIndexingListener::orderingKey(Article::class, ['id' => 'a-1']));
        self::assertNotSame($key, OutboxIndexingListener::orderingKey(Article::class, ['id' => 'a-2']));
        self::assertNotSame($key, OutboxIndexingListener::orderingKey(Article::class.'X', ['id' => 'a-1']));
        self::assertNotSame(OutboxIndexingListener::orderingKey('a', ['id' => 'b']), OutboxIndexingListener::orderingKey('ab', ['id' => '']));
    }

    private function codec(bool $mapped): SearchOutboxCodec
    {
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($mapped ? self::createStub(ObjectManager::class) : null);

        return new SearchOutboxCodec($doctrine);
    }

    /**
     * @param array<string, string|int|bool> $values
     *
     * @return array{OutboxRecord, Payload}
     */
    private function record(string $type, array $values): array
    {
        $payload = Payload::of($values);

        return [OutboxRecord::pending(RecordId::generate(), $type, $payload, null, null, new DateTimeImmutable('2026-11-24')), $payload];
    }
}
