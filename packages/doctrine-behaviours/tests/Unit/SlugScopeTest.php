<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\DoctrineBehaviours\Exception\InvalidScope;
use Phil\DoctrineBehaviours\FixedBlame;
use Phil\DoctrineBehaviours\Scope;
use Phil\DoctrineBehaviours\ScopedSluggableEntity;
use Phil\DoctrineBehaviours\SluggableEntity;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Course;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Lesson;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Tag;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SlugListener::class)]
#[CoversClass(Scope::class)]
#[CoversClass(InvalidScope::class)]
#[CoversTrait(ScopedSluggableEntity::class)]
#[CoversTrait(SluggableEntity::class)]
final class SlugScopeTest extends TestCase
{
    private EntityManager $manager;

    private Course $music;

    private Course $chess;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-10 08:00')), new FixedBlame());
        $this->music = new Course('music');
        $this->chess = new Course('chess');
        $this->manager->persist($this->music);
        $this->manager->persist($this->chess);
        $this->manager->flush();
    }

    public function testATableWideSlugHasNoScope(): void
    {
        self::assertSame([], new Tag('t', 'Name')->slugScope());
    }

    public function testTheSameSlugLivesInEachScope(): void
    {
        $lessons = [
            'music' => new Lesson('m1', 'Introduction', $this->music),
            'music again' => new Lesson('m2', 'Introduction', $this->music),
            'chess' => new Lesson('c1', 'Introduction', $this->chess),
            'music in French' => new Lesson('m3', 'Introduction', $this->music, locale: 'fr'),
            'no course' => new Lesson('n1', 'Introduction'),
            'no course again' => new Lesson('n2', 'Introduction'),
        ];

        foreach ($lessons as $lesson) {
            $this->manager->persist($lesson);
        }

        $this->manager->flush();

        self::assertSame(
            ['music' => 'introduction', 'music again' => 'introduction-2', 'chess' => 'introduction', 'music in French' => 'introduction', 'no course' => 'introduction', 'no course again' => 'introduction-2'],
            array_map(static fn (Lesson $lesson): ?string => $lesson->slug(), $lessons),
        );

        $later = new Lesson('c2', 'Introduction', $this->chess);
        $this->manager->persist($later);
        $this->manager->flush();

        self::assertSame('introduction-2', $later->slug(), 'the rows stored count');
    }

    public function testAScopeNotStoredYetIsEmpty(): void
    {
        $course = new Course('new');
        $first = new Lesson('a', 'Introduction', $course);
        $second = new Lesson('b', 'Introduction', $course);
        $this->manager->persist($course);
        $this->manager->persist($first);
        $this->manager->persist($second);
        $this->manager->flush();

        self::assertSame(['introduction', 'introduction-2'], [$first->slug(), $second->slug()]);
    }

    public function testARecordMovedToAnotherScopeKeepsItsSlugWhenFree(): void
    {
        $music = new Lesson('m1', 'Introduction', $this->music);
        $chess = new Lesson('c1', 'Openings', $this->chess);
        $this->manager->persist($music);
        $this->manager->persist($chess);
        $this->manager->flush();

        $chess->course = $this->music;
        $this->manager->flush();

        self::assertSame('openings', $chess->slug());

        $music->course = $this->chess;
        $music->locale = 'en';

        $this->manager->flush();

        self::assertSame('introduction', $music->slug());
    }

    public function testARecordMovedToAScopeWhereItsSlugIsTakenIsNumbered(): void
    {
        $music = new Lesson('m1', 'Introduction', $this->music);
        $chess = new Lesson('c1', 'Introduction', $this->chess);
        $this->manager->persist($music);
        $this->manager->persist($chess);
        $this->manager->flush();

        $chess->course = $this->music;
        $this->manager->flush();

        self::assertSame('introduction-2', $chess->slug());
        self::assertSame('introduction', $music->slug());
    }

    public function testANewSlugInTheSameScopeSkipsItsOwnOldOne(): void
    {
        $lesson = new Lesson('m1', 'Introduction', $this->music);
        $this->manager->persist($lesson);
        $this->manager->flush();

        $lesson->assignSlug(Slug::fromString('introduction'));
        $lesson->title = 'Other';

        $this->manager->flush();

        self::assertSame('introduction', $lesson->slug());

        $lesson->assignSlug(null);
        $this->manager->flush();

        self::assertSame('other', $lesson->slug());
    }

    public function testAnotherChangeLeavesTheSlugAlone(): void
    {
        $lesson = new Lesson('m1', 'Introduction', $this->music);
        $this->manager->persist($lesson);
        $this->manager->flush();

        $lesson->title = 'Renamed';
        $lesson->placeAt(5);

        $this->manager->flush();

        self::assertSame('introduction', $lesson->slug());
    }

    public function testAScopeNamesColumnsAndToOneRelationsOnly(): void
    {
        $lesson = new Lesson('m1', 'Introduction', $this->music);
        $lesson->scope = ['lessons'];

        $this->manager->persist($lesson);

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('"lessons"');
        $this->manager->flush();
    }
}
