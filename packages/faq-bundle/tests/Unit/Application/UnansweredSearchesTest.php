<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\FaqBundle\Application\QueryNormalizer;
use Phil\FaqBundle\Application\UnansweredSearches;
use Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository;
use Phil\FaqBundle\Domain\Model\UnansweredSearch;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalUnansweredSearches;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Throttles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(UnansweredSearches::class)]
final class UnansweredSearchesTest extends TestCase
{
    public function testASearchIsCountedNormalizedOnItsDayInUtc(): void
    {
        $store = new DbalUnansweredSearches(Database::entityManager()->getConnection());
        $throttle = new Throttles();
        $searches = new UnansweredSearches($store, new QueryNormalizer(), $throttle, new MockClock('2026-11-21 22:30:00', 'America/Montreal'));

        self::assertTrue($searches->record(Locale::fromString('fr_CA'), '  Où est MA commande ? '));
        self::assertTrue($searches->record(Locale::fromString('fr_CA'), 'ou est ma COMMANDE'));
        self::assertTrue($searches->record(Locale::fromString('fr_CA'), 'abc'));
        self::assertFalse($searches->record(Locale::fromString('fr_CA'), ' ab ! '));

        self::assertSame([['fr_CA', 'ou est ma commande', 2, '2026-11-22'], ['fr_CA', 'abc', 1, '2026-11-22']], array_map(static fn (UnansweredSearch $one): array => [$one->locale, $one->query, $one->searches, $one->lastSearchedOn->format('Y-m-d')], $store->mostFrequent(10)));
        self::assertSame(['fr_CA', 'fr_CA', 'fr_CA'], $throttle->keys);
    }

    public function testASearchIsLongEnoughByItsCharactersAndRecordedOnItsDayAtMidnight(): void
    {
        $repository = new class implements UnansweredSearchRepository {
            /** @var list<array{string, string, string}> */
            public array $recorded = [];

            #[Override]
            public function record(string $locale, string $query, DateTimeImmutable $day): void
            {
                $this->recorded[] = [$locale, $query, $day->format(\DATE_ATOM)];
            }

            #[Override]
            public function mostFrequent(int $limit, ?string $locale = null): array
            {
                return [];
            }

            #[Override]
            public function forget(string $locale, string $query): void
            {
            }
        };
        $searches = new UnansweredSearches($repository, new QueryNormalizer(), new Throttles(), new MockClock('2026-11-21 22:30:00', 'America/Montreal'));

        self::assertFalse($searches->record(Locale::fromString('ja'), '日本'));
        self::assertTrue($searches->record(Locale::fromString('ja'), '日本語'));
        self::assertSame([['ja', '日本語', '2026-11-22T00:00:00+00:00']], $repository->recorded);
    }
}
