<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Equivalence;

use LogicException;
use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\TrainingCompletions;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Domain\Equivalence\LanguageTag;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\EquivalenceGroup;
use Phil\CoursesBundle\Domain\Model\EquivalentCourse;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\Training\ObligationState;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEquivalenceRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The groups of equivalent courses over SQLite: one course per language, the course chosen by the learner's language,
 * and a completion recognised across the group — for the completions asked, the mandatory training and the credits.
 */
#[CoversClass(Equivalences::class)]
#[CoversClass(EquivalenceGroup::class)]
#[CoversClass(EquivalentCourse::class)]
#[CoversClass(EquivalenceGroupId::class)]
#[CoversClass(LanguageTag::class)]
#[CoversClass(DbalEquivalenceRepository::class)]
#[CoversClass(DbalCourseRepository::class)]
#[CoversClass(Completions::class)]
#[CoversClass(Completion::class)]
#[CoversClass(Assignments::class)]
#[CoversClass(Transcripts::class)]
#[CoversClass(TrainingCompletions::class)]
final class EquivalencesTest extends TestCase
{
    private Store $store;

    private Course $english;

    private Course $french;

    private Activity $englishPage;

    private Activity $frenchPage;

    protected function setUp(): void
    {
        $this->store = new Store();
        [$this->english, $this->englishPage] = $this->course('Fire safety');
        [$this->french, $this->frenchPage] = $this->course('Sécurité incendie');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tags(): iterable
    {
        yield 'a primary language' => ['en', 'en'];
        yield 'a region with an underscore' => ['fr_CA', 'fr-ca'];
        yield 'capitals and spaces' => [' FR-ca ', 'fr-ca'];
        yield 'a script and a region' => ['zh-Hant-TW', 'zh-hant-tw'];
        yield 'eight letters' => ['abcdefgh', 'abcdefgh'];
    }

    #[DataProvider('tags')]
    public function testATagIsNormalized(string $tag, string $normalized): void
    {
        self::assertSame($normalized, LanguageTag::normalize($tag));
        self::assertSame(explode('-', $normalized)[0], LanguageTag::primary($normalized));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongTags(): iterable
    {
        yield 'empty' => [''];
        yield 'one letter' => ['e'];
        yield 'nine letters' => ['abcdefghi'];
        yield 'a digit first' => ['1en'];
        yield 'an empty subtag' => ['en--ca'];
        yield 'a trailing dash' => ['en-'];
        yield 'a subtag of nine' => ['en-abcdefghi'];
        yield 'too long' => ['en-'.implode('-', array_fill(0, 9, 'abc'))];
        yield 'a newline' => ["en\nfr"];
    }

    #[DataProvider('wrongTags')]
    public function testAWrongTagIsRefused(string $tag): void
    {
        $this->expectExceptionObject(InvalidCourse::because('A language is a tag such as "en" or "fr-CA", of 35 characters at most.'));
        LanguageTag::normalize($tag);
    }

    public function testATagOfThirtyFiveCharactersIsKept(): void
    {
        $tag = 'enx-'.implode('-', array_fill(0, 8, 'abc'));
        self::assertSame(35, \strlen($tag));
        self::assertSame($tag, LanguageTag::normalize($tag));
    }

    public function testAGroupHoldsOneCoursePerLanguage(): void
    {
        $group = $this->store->equivalences->create('  Fire safety  ');
        self::assertSame('Fire safety', $group->title);
        self::assertEquals($group, $this->store->equivalences->get($group->id));
        $en = $this->store->equivalences->add($group->id, $this->english->id, 'EN');
        self::assertSame('en', $en->language);
        $this->store->equivalences->add($group->id, $this->french->id, 'fr_CA');

        self::assertEquals($en, $this->store->equivalences->membership($this->english->id));
        self::assertSame([['en', $this->english->id->value], ['fr-ca', $this->french->id->value]], array_map(static fn (EquivalentCourse $member): array => [$member->language, $member->course->value], $this->store->equivalences->courses($group->id)));
        self::assertEquals([$this->french->id], $this->store->equivalences->equivalents($this->english->id));
        self::assertEquals([$this->english->id], $this->store->equivalences->equivalents($this->french->id));

        // The same course again changes its language.
        $this->store->equivalences->add($group->id, $this->french->id, 'fr');
        self::assertSame('fr', $this->store->equivalences->membership($this->french->id)?->language);
        self::assertCount(2, $this->store->equivalences->courses($group->id));

        [$spanish] = $this->course('Seguridad');

        try {
            $this->store->equivalences->add($group->id, $spanish->id, 'FR');
            self::fail('Two courses in one language.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('The group already holds a course in "fr".', $invalidCourse->getMessage());
        }

        $other = $this->store->equivalences->create('Other');

        try {
            $this->store->equivalences->add($other->id, $this->english->id, 'de');
            self::fail('A course in two groups.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame(\sprintf('The course "%s" is already in another group of equivalent courses.', $this->english->id->value), $invalidCourse->getMessage());
        }

        self::assertSame(['Fire safety', 'Other'], array_map(static fn (EquivalenceGroup $found): string => $found->title, $this->store->equivalences->all()));
        self::assertSame(['Other'], array_map(static fn (EquivalenceGroup $found): string => $found->title, $this->store->equivalences->all(1, 1)));
        $renamed = $this->store->equivalences->rename($other->id, 'Another');
        self::assertSame('Another', $this->store->equivalences->get($other->id)->title);
        self::assertSame('Another', $renamed->title);

        $this->store->equivalences->remove($this->french->id);
        self::assertNull($this->store->equivalences->membership($this->french->id));
        self::assertSame([], $this->store->equivalences->equivalents($this->english->id));
        self::assertSame([], $this->store->equivalences->equivalents($this->french->id));

        $this->store->equivalences->dissolve($group->id);
        self::assertNull($this->store->equivalences->membership($this->english->id));
        self::assertSame([], $this->store->equivalences->courses($group->id));
        $this->expectException(UnknownRecord::class);
        $this->store->equivalences->get($group->id);
    }

    public function testAnUnknownGroupOrCourseOrAFullGroupIsRefused(): void
    {
        try {
            $this->store->equivalences->add(EquivalenceGroupId::generate(), $this->english->id, 'en');
            self::fail('Added to no group.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertStringContainsString('equivalence group', $unknownRecord->getMessage());
        }

        $group = $this->store->equivalences->create('Group');

        try {
            $this->store->equivalences->add($group->id, CourseId::generate(), 'en');
            self::fail('Added no course.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertStringContainsString('No course', $unknownRecord->getMessage());
        }

        try {
            $this->store->equivalences->rename(EquivalenceGroupId::generate(), 'Nothing');
            self::fail('Renamed no group.');
        } catch (UnknownRecord) {
        }

        for ($index = 0; $index < Equivalences::MAX_COURSES; ++$index) {
            $this->store->equivalenceStore->saveCourse(new EquivalentCourse($group->id, CourseId::generate(), 'xx-'.$index));
        }

        // A course of the group already full changes its language.
        $member = $this->store->equivalenceStore->courses($group->id)[0];
        $this->store->courseStore->save(new Course($member->course, 'Kept', $this->store->clock->now()));
        self::assertSame('zz', $this->store->equivalences->add($group->id, $member->course, 'zz')->language);

        try {
            $this->store->equivalences->add($group->id, $this->english->id, 'en');
            self::fail('A full group.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('A group holds 50 equivalent courses at most.', $invalidCourse->getMessage());
        }

        $this->expectException(InvalidCourse::class);
        $this->store->equivalences->create(' ');
    }

    public function testTheCourseIsChosenByTheLearnersLanguage(): void
    {
        [$canadian] = $this->course('Sécurité incendie (Canada)');
        $group = $this->store->equivalences->create('Fire safety');
        $this->store->equivalences->add($group->id, $this->english->id, 'en');
        $this->store->equivalences->add($group->id, $this->french->id, 'fr');
        $this->store->equivalences->add($group->id, $canadian->id, 'fr-CA');

        self::assertEquals($this->french->id, $this->store->equivalences->courseFor($this->english->id, 'fr'));
        self::assertEquals($canadian->id, $this->store->equivalences->courseFor($this->english->id, 'fr_CA'));
        self::assertEquals($this->french->id, $this->store->equivalences->courseFor($canadian->id, 'fr-BE'));
        self::assertEquals($this->english->id, $this->store->equivalences->courseFor($this->french->id, 'en-GB'));
        self::assertEquals($this->french->id, $this->store->equivalences->courseFor($this->french->id, 'de'));
        self::assertEquals($this->english->id, $this->store->equivalences->courseFor($this->english->id, null));
        self::assertEquals($this->english->id, $this->store->equivalences->courseFor($this->english->id, 'not a tag'));

        [$alone] = $this->course('Alone');
        self::assertEquals($alone->id, $this->store->equivalences->courseFor($alone->id, 'fr'));
    }

    public function testACompletionIsRecognisedAcrossTheGroup(): void
    {
        $ada = LearnerId::of('ada');
        self::assertNull($this->store->courseCompletions->completion($this->english->id, $ada));

        $this->group();
        $french = $this->store->enrollments->enroll($this->french->id, $ada);
        self::assertNull($this->store->courseCompletions->completion($this->english->id, $ada));
        $this->store->enrollments->record($french, $this->frenchPage, Outcome::completed());

        $completion = $this->store->courseCompletions->completion($this->english->id, $ada) ?? throw new LogicException('Not recognised.');
        self::assertEquals([$french->id, $this->french->id, $ada], [$completion->enrollment, $completion->course, $completion->learner]);
        self::assertNull($this->store->courseCompletions->completion($this->english->id, LearnerId::of('bob')));

        // The course itself, once completed, answers for itself.
        $this->store->clock->modify('+1 day');
        $english = $this->store->enrollments->enroll($this->english->id, $ada);
        $this->store->enrollments->record($english, $this->englishPage, Outcome::completed());
        self::assertEquals($english->id, $this->store->courseCompletions->completion($this->english->id, $ada)?->enrollment);

        // Of two equivalent courses completed, the earliest.
        [$spanish, $spanishPage] = $this->course('Seguridad');
        $group = ($this->store->equivalences->membership($this->english->id) ?? throw new LogicException('No group.'))->group;
        $this->store->equivalences->add($group, $spanish->id, 'es');
        $this->store->clock->modify('-3 days');

        $early = $this->store->enrollments->enroll($spanish->id, $ada);
        $this->store->enrollments->record($early, $spanishPage, Outcome::completed());
        $this->store->clock->modify('+5 days');
        [$german, $germanPage] = $this->course('Brandschutz');
        $this->store->equivalences->add($group, $german->id, 'de');
        $late = $this->store->enrollments->enroll($german->id, LearnerId::of('ada'));
        $this->store->enrollments->record($late, $germanPage, Outcome::completed());
        [$italian] = $this->course('Sicurezza');
        $this->store->equivalences->add($group, $italian->id, 'it');
        self::assertEquals($early->id, $this->store->courseCompletions->completion($italian->id, $ada)?->enrollment);

        $alone = new Completions($this->store->enrollmentStore);
        self::assertNull($alone->completion($italian->id, $ada));
    }

    public function testAnEquivalentCourseFulfilsTheObligationAndIsCreditedOnce(): void
    {
        $this->group();
        $this->store->transcripts->credit($this->english->id, Credits::of('2', CreditUnit::Hours));
        $ada = LearnerId::of('ada');
        $assignment = $this->store->assignments->assign($this->english->id, AssigneeKind::Learner, 'ada', $this->store->clock->now()->modify('+30 days'), 12);
        $this->store->notices->take();

        $this->store->clock->modify('+1 day');

        $french = $this->store->enrollments->enroll($this->french->id, $ada);
        $this->complete($french, $this->frenchPage);

        $obligation = $this->store->assignmentStore->obligation($assignment->id, $ada);
        self::assertSame(['2026-11-14 10:00', '2027-11-14 10:00'], [$obligation?->completedAt?->format('Y-m-d H:i'), $obligation?->renewsAt?->format('Y-m-d H:i')]);
        self::assertSame(ObligationState::Completed, $this->store->assignments->of($ada)[0]->state);
        $entries = $this->store->transcripts->of($ada)->entries;
        self::assertCount(1, $entries);
        self::assertSame([$this->french->id->value, '2', 'hours', $assignment->id->value], [$entries[0]->course->value, $entries[0]->credits?->amount(), $entries[0]->credits?->unit->value, $entries[0]->assignment?->value]);

        // The English course completed afterwards fulfils nothing more and is not credited again.
        $this->store->clock->modify('+1 day');
        $english = $this->store->enrollments->get($this->store->enrollments->of($this->english->id, $ada)->id ?? throw new LogicException('Not enrolled.'));
        $this->complete($english, $this->englishPage);
        $entries = $this->store->transcripts->of($ada)->entries;
        self::assertCount(2, $entries);
        self::assertSame([$this->english->id->value, null, null], [$entries[0]->course->value, $entries[0]->credits, $entries[0]->assignment]);

        // A course of its own credits keeps them.
        $this->store->transcripts->credit($this->french->id, Credits::of('1', CreditUnit::Ceu));
        $bob = LearnerId::of('bob');
        $this->complete($this->store->enrollments->enroll($this->french->id, $bob), $this->frenchPage);
        self::assertSame('ceu', $this->store->transcripts->of($bob)->entries[0]->credits?->unit->value);
    }

    public function testAMemberWhoCompletedAnEquivalentCourseStartsCompleted(): void
    {
        $this->group();
        $ada = LearnerId::of('ada');
        $this->store->enrollments->record($this->store->enrollments->enroll($this->french->id, $ada), $this->frenchPage, Outcome::completed());
        $this->store->clock->modify('+2 days');
        $this->store->enrollments->record($this->store->enrollments->enroll($this->french->id, LearnerId::of('bob')), $this->frenchPage, Outcome::completed());
        [$spanish, $spanishPage] = $this->course('Seguridad');
        $group = ($this->store->equivalences->membership($this->english->id) ?? throw new LogicException('No group.'))->group;
        $this->store->equivalences->add($group, $spanish->id, 'es');
        $this->store->clock->modify('+1 day');
        $this->store->enrollments->record($this->store->enrollments->enroll($spanish->id, $ada), $spanishPage, Outcome::completed());
        $this->store->clock->modify('-1 day');
        $this->store->members->groups['crew'] = ['ada', 'cy'];

        $assignment = $this->store->assignments->assign($this->english->id, AssigneeKind::Group, 'crew', $this->store->clock->now()->modify('+30 days'), 6);

        // The latest completion of an equivalent course sets the renewal.
        $obligation = $this->store->assignmentStore->obligation($assignment->id, $ada);
        self::assertSame(['2026-11-16 10:00', '2027-05-16 10:00'], [$obligation?->completedAt?->format('Y-m-d H:i'), $obligation?->renewsAt?->format('Y-m-d H:i')]);
        self::assertNull($this->store->assignmentStore->obligation($assignment->id, LearnerId::of('cy'))?->completedAt);
        self::assertSame(['assigned cy '.$this->english->id->value.' 1'], $this->store->notices->take());
    }

    public function testRemovingACourseTakesItOutOfItsGroup(): void
    {
        $this->group();
        $this->store->courseStore->remove($this->french->id);
        self::assertNull($this->store->equivalences->membership($this->french->id));
        self::assertSame([], $this->store->equivalences->equivalents($this->english->id));
    }

    private function group(): EquivalenceGroup
    {
        $group = $this->store->equivalences->create('Fire safety');
        $this->store->equivalences->add($group->id, $this->english->id, 'en');
        $this->store->equivalences->add($group->id, $this->french->id, 'fr');

        return $group;
    }

    private function complete(Enrollment $enrollment, Activity $page): void
    {
        $this->store->enrollments->record($enrollment, $page, Outcome::completed());
        $events = $this->store->events->of(CourseCompleted::class);
        $this->store->completions->__invoke($events[\count($events) - 1]);
    }

    /**
     * @return array{Course, Activity}
     */
    private function course(string $title): array
    {
        $course = $this->store->courses->create($title);
        $module = $this->store->courses->addModule($course->id, 'Basics');

        return [$course, $this->store->courses->addActivity($module->id, 'Read', 'page')];
    }
}
