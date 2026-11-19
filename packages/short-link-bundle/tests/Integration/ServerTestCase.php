<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The bundle's mapping and queries on a real server, read from the environment: the table created from the mapping,
 * what is kept read back in UTC, the code kept once, the count of a visit under the link's terms, the search with its
 * wildcards escaped, and requests in separate processes racing for the last visits.
 */
abstract class ServerTestCase extends TestCase
{
    private const int RACERS = 8;

    private Server $server;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $this->server = Server::connect($this->url());
        $this->server->recreate();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->server)) {
            $this->server->entityManager->getConnection()->close();
        }
    }

    public function testALinkIsKeptAsItWasGiven(): void
    {
        $link = new ShortLink('abc123', 'https://example.com/page?x=1', $this->at('12:00'), new LinkTerms(RedirectStatus::PermanentRedirect, QueryPolicy::Keep, 'Affiche à l’entrée', 'fr-CA', false, new DateTimeImmutable('2026-11-12 08:00 America/Toronto'), $this->at('18:00'), 5));
        $this->server->repository()->save($link);
        $this->server->entityManager->clear();

        $read = $this->server->repository()->findByCode('abc123');
        self::assertInstanceOf(ShortLink::class, $read);
        self::assertEquals($link->terms(), $read->terms());
        self::assertSame('https://example.com/page?x=1', $read->target());
        self::assertSame('2026-11-12 13:00:00', $this->server->entityManager->getConnection()->fetchOne('SELECT valid_from FROM '.ShortLink::TABLE));
        self::assertNull($this->server->repository()->findByCode('ABC123'), 'a code is found in its own case only');
        self::assertTrue($this->server->repository()->isTaken('abc123'));
        self::assertFalse($this->server->repository()->isTaken('abc124'));
    }

    public function testACodeIsKeptOnce(): void
    {
        $this->server->repository()->save(new ShortLink('abc123', 'https://example.com/', $this->at('12:00')));

        $this->expectException(UniqueConstraintViolationException::class);
        Server::connect($this->url())->repository()->save(new ShortLink('abc123', 'https://example.fr/', $this->at('12:00')));
    }

    public function testAVisitIsCountedUnderTheTermsOfTheLink(): void
    {
        $link = new ShortLink('abc123', 'https://example.com/', $this->at('10:00'), new LinkTerms(validFrom: $this->at('11:00'), validUntil: $this->at('13:00'), maxVisits: 2));
        $closed = new ShortLink('closed', 'https://example.com/', $this->at('10:00'), new LinkTerms(enabled: false));
        $repository = $this->server->repository();
        $repository->save($link);
        $repository->save($closed);

        $id = (int) $link->id();

        self::assertFalse($repository->recordVisit($id, $this->at('10:59')));
        self::assertTrue($repository->recordVisit($id, new DateTimeImmutable('2026-11-12 06:00 America/Toronto')), 'at its start, in another zone');
        self::assertFalse($repository->recordVisit($id, $this->at('13:00')));
        self::assertTrue($repository->recordVisit($id, $this->at('12:59')));
        self::assertFalse($repository->recordVisit($id, $this->at('12:00')));
        self::assertFalse($repository->recordVisit((int) $closed->id(), $this->at('12:00')));
        $this->server->entityManager->clear();

        self::assertSame(2, $repository->find($id)?->visits());
        self::assertEquals($this->at('12:59'), $repository->find($id)->lastVisitAt());
    }

    public function testAMachineIsCountedApartWithoutUsingUpTheMaximum(): void
    {
        $link = new ShortLink('poster', 'https://example.com/', $this->at('10:00'), new LinkTerms(maxVisits: 1));
        $repository = $this->server->repository();
        $repository->save($link);

        $id = (int) $link->id();

        self::assertTrue($repository->recordVisit($id, $this->at('11:00'), VisitorClass::Robot));
        self::assertTrue($repository->recordVisit($id, $this->at('11:01'), VisitorClass::LinkPreview));
        self::assertTrue($repository->recordVisit($id, $this->at('11:02')));
        self::assertFalse($repository->recordVisit($id, $this->at('11:03'), VisitorClass::Prefetch));
        $this->server->entityManager->clear();

        self::assertSame([1, 2], [$repository->find($id)?->visits(), $repository->find($id)?->robotVisits()]);
        self::assertEquals($this->at('11:02'), $repository->find($id)?->lastVisitAt());
    }

    public function testTheSearchEscapesItsWildcards(): void
    {
        $repository = $this->server->repository();
        $repository->save(new ShortLink('first', 'https://example.com/a', $this->at('10:00'), new LinkTerms(label: '100% sûr')));
        $repository->save(new ShortLink('second', 'https://example.com/b_c', $this->at('11:00')));
        $repository->save(new ShortLink('third', 'https://example.fr/', $this->at('11:00'), new LinkTerms(label: 'Point!')));

        $this->server->entityManager->clear();

        self::assertSame(['third', 'second', 'first'], array_map(static fn (ShortLink $link): string => $link->code(), $repository->page('', 0, 10)));
        self::assertSame(1, $repository->count('100%'));
        self::assertSame(0, $repository->count('1%%'));
        self::assertSame(0, $repository->count('a_c'));
        self::assertSame(1, $repository->count('Point!'));
        self::assertSame(2, $repository->count('example.com'));
    }

    public function testRequestsRacingForTheLastVisitsCountThemOnce(): void
    {
        $link = new ShortLink('race', 'https://example.com/', new DateTimeImmutable('-1 day'), new LinkTerms(maxVisits: 3));
        $this->server->repository()->save($link);
        $moment = \sprintf('%.6F', microtime(true) + 1.5);
        $processes = [];

        for ($racer = 0; $racer < self::RACERS; ++$racer) {
            $process = new Process([\PHP_BINARY, __DIR__.'/visit.php', $this->url(), (string) $link->id(), $moment]);
            $process->start();
            $processes[] = $process;
        }

        $answers = [];

        foreach ($processes as $process) {
            $process->wait();
            $answers[] = trim($process->getOutput()).trim($process->getErrorOutput());
        }

        sort($answers);
        self::assertSame([...array_fill(0, 3, 'counted'), ...array_fill(0, self::RACERS - 3, 'refused')], $answers);
        $visits = $this->server->entityManager->getConnection()->fetchOne('SELECT visits FROM '.ShortLink::TABLE.' WHERE code = ?', ['race']);
        self::assertIsNumeric($visits);
        self::assertSame(3, (int) $visits);
    }

    private function url(): string
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $url;
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-12 '.$time.' UTC');
    }
}
