<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Label;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Ledger;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\LedgerDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Memo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

/**
 * How a reference's identifier is rebuilt before a lookup, on SQLite in memory: a string the identifier
 * refuses finds no root, without a query.
 */
#[CoversClass(DoctrineContentRootRepository::class)]
final class DoctrineContentRootRepositoryTest extends TestCase
{
    private DoctrineContentRootRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        $roots = [Article::class, Memo::class, Label::class, Ledger::class];
        $entityManager = EntityManagerFactory::create(roots: $roots);
        $this->repository = new DoctrineContentRootRepository($entityManager, ContentRoots::fromClassNames($roots));

        // Only the ledger has a table: a lookup of any other kind that reached the database would fail.
        new SchemaTool($entityManager)->createSchema([
            $entityManager->getClassMetadata(Ledger::class),
            $entityManager->getClassMetadata(LedgerDimension::class),
        ]);
        $entityManager->getConnection()->executeStatement('INSERT INTO phil_content_probe_ledger (id) VALUES (0), (123456789012345678), (1234567890123456789)');
    }

    public function testAnIdentifierAUuidRefusesFindsNoRoot(): void
    {
        self::assertNull($this->repository->find(ContentReference::of('memo', 'abc')));
    }

    public function testAnIdentifierAnIdentifierOfTheDomainRefusesFindsNoRoot(): void
    {
        self::assertNull($this->repository->find(ContentReference::of('article', 'abc')));
    }

    public function testAnIdentifierThatRefusesWithADomainErrorFindsNoRoot(): void
    {
        self::assertNull($this->repository->find(ContentReference::of('label', 'abc')));
    }

    public function testAnIntegerIdentifierIsEighteenDigitsAtMost(): void
    {
        self::assertInstanceOf(Ledger::class, $this->repository->find(ContentReference::of('ledger', '123456789012345678')));
        self::assertInstanceOf(Ledger::class, $this->repository->find(ContentReference::of('ledger', '0')));
        self::assertNull($this->repository->find(ContentReference::of('ledger', '1234567890123456789')), 'Nineteen digits may not fit an integer.');
        self::assertNull($this->repository->find(ContentReference::of('ledger', 'abc')), 'Not the root 0.');
        self::assertNull($this->repository->find(ContentReference::of('ledger', '12a')));
    }

    /**
     * An identifier that is neither an integer, a string nor `Stringable` makes no reference.
     */
    public function testAnIdentifierWithoutStringFormMakesNoReference(): void
    {
        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('getIdentifierValues')->willReturn(['id' => 1.5]);
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The identifier of "'.Article::class.'" has no string form; got float.');

        new DoctrineContentRootRepository($entityManager, ContentRoots::fromClassNames([Article::class]))->referenceOf(new Article());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function identifiersWithAStringForm(): iterable
    {
        yield 'an integer' => [42, '42'];
        yield 'a string' => ['abc', 'abc'];
        yield 'a Stringable' => [new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        }, 'stringable'];
    }

    /**
     * An integer, a string or a `Stringable` identifier makes the reference of its root.
     */
    #[DataProvider('identifiersWithAStringForm')]
    public function testAnIdentifierWithAStringFormMakesAReference(mixed $identifier, string $expected): void
    {
        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('getIdentifierValues')->willReturn(['id' => $identifier]);
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        $reference = new DoctrineContentRootRepository($entityManager, ContentRoots::fromClassNames([Article::class]))->referenceOf(new Article());

        self::assertSame('article:'.$expected, $reference->toString());
    }
}
