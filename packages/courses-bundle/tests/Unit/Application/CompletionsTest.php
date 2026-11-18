<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Application;

use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Completions::class)]
#[CoversClass(Completion::class)]
final class CompletionsTest extends TestCase
{
    public function testACompletionIsAnsweredByTheRuleThatRaisesCourseCompleted(): void
    {
        $store = new Store();
        $completions = new Completions($store->enrollmentStore);
        $course = $store->courses->create('Knots');
        $module = $store->courses->addModule($course->id, 'Basics');
        $first = $store->courses->addActivity($module->id, 'Reef knot', 'page');
        $second = $store->courses->addActivity($module->id, 'Bowline', 'page');
        $ada = LearnerId::of('ada');

        self::assertNull($completions->completion($course->id, $ada));

        $enrollment = $store->enrollments->enroll($course->id, $ada);
        $store->learning->complete($enrollment, $first->id);
        self::assertNull($completions->completion($course->id, $ada));

        $store->clock->modify('+2 hours');
        $store->learning->complete($enrollment, $second->id);

        $completion = $completions->completion($course->id, $ada);

        self::assertInstanceOf(Completion::class, $completion);
        self::assertSame($enrollment->id->value, $completion->enrollment->value);
        self::assertSame($course->id->value, $completion->course->value);
        self::assertSame('ada', $completion->learner->value);
        self::assertSame('2026-11-13T12:00:00+00:00', $completion->completedAt->format(\DATE_ATOM));

        $raised = $store->events->of(CourseCompleted::class);
        self::assertCount(1, $raised);
        self::assertSame([$raised[0]->enrollment, $raised[0]->course, $raised[0]->learner], [$completion->enrollment->value, $completion->course->value, $completion->learner->value]);

        self::assertNull($completions->completion($course->id, LearnerId::of('grace')));
        self::assertNull($completions->completion(CourseId::generate(), $ada));
    }

    public function testACompletionStaysWhenTheCourseGainsAnActivity(): void
    {
        $store = new Store();
        $completions = new Completions($store->enrollmentStore);
        $course = $store->courses->create('Knots');
        $module = $store->courses->addModule($course->id, 'Basics');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->learning->complete($enrollment, $store->courses->addActivity($module->id, 'Reef knot', 'page')->id);
        $store->courses->addActivity($module->id, 'Bowline', 'page');

        self::assertSame($enrollment->id->value, $completions->completion($course->id, LearnerId::of('ada'))?->enrollment->value);
    }
}
