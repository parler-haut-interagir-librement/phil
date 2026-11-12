<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Scheduling;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\ScheduledPublication;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Scheduling\PublicationReferences;
use Phil\ContentBundle\Infrastructure\Scheduling\PublicationSubjects;
use Phil\ContentBundle\Infrastructure\Scheduling\ScheduledTransitionsPublicationSchedule;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryScheduledTransitions;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PublicationReferences::class)]
#[CoversClass(PublicationSubjects::class)]
#[CoversClass(ScheduledTransitionsPublicationSchedule::class)]
#[CoversClass(ScheduledPublication::class)]
final class PublicationScheduleTest extends TestCase
{
    public function testAPublicationIsNamedByItsKindItsIdentifierAndItsLocale(): void
    {
        $references = new PublicationReferences(['news']);
        $resource = $references->referenceOf(Publication::of(ContentReference::of('news', '42'), 'fr_CA'));

        self::assertSame('news:42@fr_CA', $resource->toString());

        $publication = $references->publicationOf($resource);
        self::assertNotNull($publication);
        self::assertTrue($publication->reference->equals(ContentReference::of('news', '42')));
        self::assertSame('fr_CA', $publication->locale->value);
    }

    public function testTheKindsAreReadFromTheRoots(): void
    {
        $references = PublicationReferences::fromRoots(ContentRoots::fromClassNames([Article::class]));
        $key = ContentRoots::fromClassNames([Article::class])->resourceKeys()[0];

        self::assertNotNull($references->publicationOf(ResourceReference::of($key, '1@fr')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notPublications(): iterable
    {
        yield 'another kind' => ['page', '42@fr'];
        yield 'no locale' => ['news', '42'];
        yield 'two locales' => ['news', '42@fr@en'];
        yield 'a malformed identifier' => ['news', '4 2@fr'];
        yield 'a malformed locale' => ['news', '42@f'];
    }

    #[DataProvider('notPublications')]
    public function testWhatIsNotAPublicationIsLeftToOtherResolvers(string $key, string $id): void
    {
        $subjects = new PublicationSubjects(new PublicationReferences(['news']));

        self::assertNull($subjects->resolve(ResourceReference::of($key, $id)));
    }

    public function testTheResolverHandsThePublicationBack(): void
    {
        $subjects = new PublicationSubjects(new PublicationReferences(['news']));
        $publication = $subjects->resolve(ResourceReference::of('news', '42@fr'));

        self::assertInstanceOf(Publication::class, $publication);
        $subjects->saved($publication);
    }

    public function testThePublicationIsScheduledRescheduledAndCancelled(): void
    {
        $schedule = $this->schedule();
        $publication = Publication::of(ContentReference::of('news', '42'), 'fr');
        $other = Publication::of(ContentReference::of('news', '42'), 'en');

        $schedule->schedule($publication, 'unpublish', new DateTimeImmutable('2026-12-31 23:00 UTC'));
        $schedule->schedule($publication, 'publish', new DateTimeImmutable('2026-12-02 08:00 UTC'));
        $schedule->schedule($publication, 'publish', new DateTimeImmutable('2026-12-01 08:00 UTC'));

        $pending = $schedule->pending($publication);
        self::assertSame(['publish', 'unpublish'], array_map(static fn (ScheduledPublication $planned): string => $planned->transition, $pending));
        self::assertSame('2026-12-01 08:00', $pending[0]->dueAt->format('Y-m-d H:i'));
        self::assertSame([], $schedule->pending($other));

        self::assertSame(1, $schedule->cancel($publication, 'publish'));
        self::assertSame(['unpublish'], array_map(static fn (ScheduledPublication $planned): string => $planned->transition, $schedule->pending($publication)));
        self::assertSame(1, $schedule->cancel($publication));
        self::assertSame([], $schedule->pending($publication));
    }

    public function testOnlyPublishingAndUnpublishingAreScheduled(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('A publication is scheduled to "publish" or to "unpublish", not to "remove_draft".');

        $this->schedule()->schedule(Publication::of(ContentReference::of('news', '42'), 'fr'), 'remove_draft', new DateTimeImmutable('2026-12-01 08:00 UTC'));
    }

    public function testTheMessageCutsALongTransitionByCharacters(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('A publication is scheduled to "publish" or to "unpublish", not to "'.str_repeat('é', 64).'".');

        $this->schedule()->schedule(Publication::of(ContentReference::of('news', '42'), 'fr'), str_repeat('é', 70), new DateTimeImmutable('2026-12-01 08:00 UTC'));
    }

    private function schedule(): ScheduledTransitionsPublicationSchedule
    {
        return new ScheduledTransitionsPublicationSchedule(
            new Scheduler(new InMemoryScheduledTransitions(), [], new MockClock('2026-11-11 12:00 UTC')),
            new PublicationReferences(['news']),
        );
    }
}
