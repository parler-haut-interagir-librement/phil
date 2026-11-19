<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use Phil\ShortLinkBundle\Infrastructure\Doctrine\DoctrineShortLinkRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The links on SQLite in memory, their table created from the mapping, each read with the entity manager cleared.
 */
#[CoversClass(DoctrineShortLinkRepository::class)]
#[CoversClass(ShortLink::class)]
final class DoctrineShortLinkRepositoryTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineShortLinkRepository $repository;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->repository = new DoctrineShortLinkRepository($this->entityManager);
    }

    public function testALinkIsKeptAsItWasGiven(): void
    {
        $link = new ShortLink('abc123', 'https://example.com/', $this->at('12:00'), new LinkTerms(RedirectStatus::PermanentRedirect, QueryPolicy::Keep, 'Affiche', 'fr', false, new DateTimeImmutable('2026-11-12 08:00 America/Toronto'), $this->at('18:00'), 5));
        $this->repository->save($link);
        $id = $link->id();
        self::assertIsInt($id);
        $this->entityManager->clear();

        $read = $this->repository->find($id);
        self::assertInstanceOf(ShortLink::class, $read);
        self::assertNotSame($link, $read);
        self::assertEquals($link->terms(), $read->terms());
        self::assertSame('2026-11-12 13:00:00', $this->entityManager->getConnection()->fetchOne('SELECT valid_from FROM '.ShortLink::TABLE), 'kept in UTC');
        self::assertSame($id, $this->repository->findByCode('abc123')?->id());
        self::assertNull($this->repository->findByCode('ABC123'));
        self::assertTrue($this->repository->isTaken('abc123'));
        self::assertFalse($this->repository->isTaken('ABC123'), 'SQLite compares text with its case');
        self::assertNull($this->repository->find($id + 1));
    }

    public function testACodeFindsItsOwnLinkAmongOthers(): void
    {
        $this->repository->save(new ShortLink('first', 'https://example.com/a', $this->at('10:00')));
        $this->repository->save(new ShortLink('second', 'https://example.com/b', $this->at('11:00')));

        $this->entityManager->clear();

        self::assertSame('second', $this->repository->findByCode('second')?->code());
        self::assertSame('first', $this->repository->findByCode('first')?->code());
        self::assertNull($this->repository->findByCode('third'));
    }

    public function testACodeIsKeptOnce(): void
    {
        $this->repository->save(new ShortLink('abc123', 'https://example.com/', $this->at('12:00')));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->repository->save(new ShortLink('abc123', 'https://example.fr/', $this->at('12:00')));
    }

    public function testAVisitIsCountedWhileTheLinkIsInForce(): void
    {
        $link = new ShortLink('abc123', 'https://example.com/', $this->at('10:00'), new LinkTerms(validFrom: $this->at('11:00'), validUntil: $this->at('13:00'), maxVisits: 2));
        $this->repository->save($link);
        $id = (int) $link->id();

        self::assertFalse($this->repository->recordVisit($id, $this->at('10:59')), 'before its start');
        self::assertTrue($this->repository->recordVisit($id, $this->at('11:00')), 'at its start');
        self::assertFalse($this->repository->recordVisit($id, $this->at('13:00')), 'at its end');
        self::assertTrue($this->repository->recordVisit($id, $this->at('12:59')));
        self::assertFalse($this->repository->recordVisit($id, $this->at('12:00')), 'out of visits');
        self::assertFalse($this->repository->recordVisit($id + 1, $this->at('12:00')), 'a link that is not there');
        $this->entityManager->clear();

        $read = $this->repository->find($id);
        self::assertSame(2, $read?->visits());
        self::assertEquals($this->at('12:59'), $read->lastVisitAt());
    }

    public function testAMachineIsCountedApartAndNeverUsesUpTheMaximum(): void
    {
        $link = new ShortLink('poster', 'https://example.com/', $this->at('10:00'), new LinkTerms(validFrom: $this->at('11:00'), maxVisits: 1));
        $this->repository->save($link);
        $id = (int) $link->id();

        self::assertFalse($this->repository->recordVisit($id, $this->at('10:59'), VisitorClass::Robot), 'before its start');
        self::assertTrue($this->repository->recordVisit($id, $this->at('11:00'), VisitorClass::Robot));
        self::assertTrue($this->repository->recordVisit($id, $this->at('11:01'), VisitorClass::LinkPreview));
        self::assertTrue($this->repository->recordVisit($id, $this->at('11:02'), VisitorClass::Human));
        self::assertFalse($this->repository->recordVisit($id, $this->at('11:03'), VisitorClass::Prefetch), 'out of visits');
        $this->entityManager->clear();

        $read = $this->repository->find($id);
        self::assertSame([1, 2], [$read?->visits(), $read?->robotVisits()]);
        self::assertEquals($this->at('11:02'), $read?->lastVisitAt());
    }

    public function testADisabledLinkCountsNothingAndALinkWithoutTermsCountsEverything(): void
    {
        $closed = new ShortLink('closed', 'https://example.com/', $this->at('10:00'), new LinkTerms(enabled: false));
        $open = new ShortLink('open', 'https://example.com/', $this->at('10:00'));
        $this->repository->save($closed);
        $this->repository->save($open);

        self::assertFalse($this->repository->recordVisit((int) $closed->id(), $this->at('12:00')));

        for ($visit = 0; $visit < 5; ++$visit) {
            self::assertTrue($this->repository->recordVisit((int) $open->id(), $this->at('12:00')));
        }

        $visits = $this->entityManager->getConnection()->fetchOne('SELECT visits FROM '.ShortLink::TABLE.' WHERE code = ?', ['open']);
        self::assertIsNumeric($visits);
        self::assertSame(5, (int) $visits);
    }

    public function testThePagesListTheNewestFirstAndSearchWithoutWildcards(): void
    {
        $this->repository->save(new ShortLink('first', 'https://example.com/a', $this->at('10:00'), new LinkTerms(label: '100% sûr')));
        $this->repository->save(new ShortLink('second', 'https://example.com/b_c', $this->at('11:00')));
        $this->repository->save(new ShortLink('third', 'https://example.fr/', $this->at('11:00'), new LinkTerms(label: 'Point!')));

        $this->entityManager->clear();

        self::assertSame(['third', 'second', 'first'], $this->codes($this->repository->page('', 0, 10)));
        self::assertSame(['second'], $this->codes($this->repository->page('', 1, 1)));
        self::assertSame(['third'], $this->codes($this->repository->page('', -5, 0)), 'the bounds are kept to a first page of one');
        self::assertSame(3, $this->repository->count(''));
        self::assertSame(['second', 'first'], $this->codes($this->repository->page('example.com', 0, 10)));
        self::assertSame(['first'], $this->codes($this->repository->page('100%', 0, 10)));
        self::assertSame(0, $this->repository->count('1%%'));
        self::assertSame(['second'], $this->codes($this->repository->page('b_c', 0, 10)));
        self::assertSame(0, $this->repository->count('a_c'), 'an underscore is no wildcard');
        self::assertSame(['third'], $this->codes($this->repository->page('Point!', 0, 10)));
        self::assertSame(0, $this->repository->count('Point!!'));
        self::assertSame(['third'], $this->codes($this->repository->page('thi', 0, 10)));
        self::assertSame(1, $this->repository->count('fr'));
    }

    public function testALinkIsRemoved(): void
    {
        $link = new ShortLink('abc123', 'https://example.com/', $this->at('12:00'));
        $this->repository->save($link);

        self::assertTrue($this->repository->remove((int) $link->id()));
        self::assertFalse($this->repository->remove((int) $link->id()));
        self::assertSame(0, $this->repository->count(''));
    }

    /**
     * @param list<ShortLink> $links
     *
     * @return list<string>
     */
    private function codes(array $links): array
    {
        return array_map(static fn (ShortLink $link): string => $link->code(), $links);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-12 '.$time.' UTC');
    }
}
