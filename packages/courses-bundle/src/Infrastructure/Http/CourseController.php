<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\Availabilities;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\Scorm\ScormLaunch;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\EquivalentCourse;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The pages of a course, for the learner signed in: the outline with where each activity stands, the enrollment when
 * the site lets learners enroll themselves, the activity page that frames or opens an activity, or an ended SCORM
 * attempt for review, the other courses of its group of equivalent courses with the address that leads to the one of
 * the learner's language, and the forms that mark an activity done and ask for a second chance. Every change is posted with a CSRF token. Content of the
 * packages is framed only from the content origin, never from the application's own.
 */
final readonly class CourseController
{
    public const string OUTLINE_ROUTE = 'phil_courses_outline';

    public const string ENROLL_ROUTE = 'phil_courses_enroll';

    public const string LAUNCH_ROUTE = 'phil_courses_launch';

    public const string COMPLETE_ROUTE = 'phil_courses_complete';

    public const string RETRY_ROUTE = 'phil_courses_retry';

    public const string REVIEW_ROUTE = 'phil_courses_review';

    public const string LANGUAGE_ROUTE = 'phil_courses_language';

    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private Courses $courses,
        private Enrollments $enrollments,
        private Availabilities $availabilities,
        private Learning $learning,
        private SecondChances $secondChances,
        private ActivityTypes $types,
        private CurrentLearner $current,
        private CourseRequest $request,
        private ContentUrls $content,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private bool $selfEnrollment = false,
        private bool $showLocked = true,
        private ?Equivalences $equivalences = null,
    ) {
    }

    public function outline(string $course): Response
    {
        $learner = $this->learner();
        $found = $this->course($course);
        $enrollment = $this->enrollments->of($found->id, $learner);

        return $this->render('@PhilCourses/outline.html.twig', [
            'course' => $found,
            'enrollment' => $enrollment,
            'statuses' => $enrollment instanceof Enrollment ? $this->availabilities->outline($enrollment) : [],
            'outline' => $this->courses->outline($found->id),
            'percent' => $enrollment instanceof Enrollment ? $this->enrollments->percent($enrollment) : 0,
            'reviewable' => $enrollment instanceof Enrollment ? $this->learning->reviewable($enrollment) : [],
            'self_enrollment' => $this->selfEnrollment,
            'show_locked' => $this->showLocked,
            'equivalents' => $this->equivalents($found->id),
        ]);
    }

    /**
     * The course of the group of equivalent courses given in the language of the request, else the course itself.
     */
    public function language(Request $request, string $course): RedirectResponse
    {
        $this->learner();
        $found = $this->course($course);

        return $this->back($this->equivalences?->courseFor($found->id, $request->getLocale()) ?? $found->id);
    }

    public function enroll(Request $request, string $course): RedirectResponse
    {
        $this->trust($request);
        $learner = $this->learner();
        $found = $this->course($course);

        if (!$this->selfEnrollment) {
            throw new AccessDeniedException('Learners do not enroll themselves on this site.');
        }

        $this->enrollments->enroll($found->id, $learner);

        return $this->back($found->id);
    }

    public function launch(Request $request, string $course, string $activity): Response
    {
        return $this->take($request, $course, $activity, false);
    }

    /**
     * The learner's last ended attempt at a SCORM activity, framed for review.
     */
    public function review(Request $request, string $course, string $activity): Response
    {
        return $this->take($request, $course, $activity, true);
    }

    private function take(Request $request, string $course, string $activity, bool $review): Response
    {
        $this->trust($request);
        [$found, $enrollment, $activityId] = $this->enrolled($course, $activity);

        try {
            $launch = $review ? $this->learning->review($enrollment, $activityId) : $this->learning->launch($enrollment, $activityId);
        } catch (ActivityUnavailable $activityUnavailable) {
            return $this->render('@PhilCourses/activity.html.twig', ['course' => $found, 'activity' => $this->courses->activity($activityId), 'launch' => null, 'error' => $activityUnavailable->getMessage()], Response::HTTP_CONFLICT);
        } catch (RecordStoreUnavailable) {
            return $this->render('@PhilCourses/activity.html.twig', ['course' => $found, 'activity' => $this->courses->activity($activityId), 'launch' => null, 'error' => 'The launch could not be recorded: try again later.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (LaunchWindow::Embedded === $launch->window && $this->content->origin() === $request->getSchemeAndHttpHost()) {
            return $this->render('@PhilCourses/activity.html.twig', ['course' => $found, 'activity' => $this->courses->activity($activityId), 'launch' => null, 'error' => 'The content origin is the site itself: the content is not framed.'], Response::HTTP_CONFLICT);
        }

        $taken = $this->courses->activity($activityId);

        return $this->render('@PhilCourses/activity.html.twig', [
            'course' => $found,
            'activity' => $taken,
            'launch' => $launch,
            'error' => null,
            'manual' => !$review && $this->types->get($taken->type)->completesManually(),
            'scorm' => $this->scorm($launch, $found, $taken),
        ]);
    }

    /**
     * What the page hands the Stimulus controller of a SCORM session: the frame of the run time on the content origin,
     * the state of the session, and the addresses of the commits, of the launch of another activity and of the outline.
     *
     * @return ?array{frame: string, origin: string, state: string, commit: string, launch: string, outline: string, number: int, resumed: bool, review: bool}
     */
    private function scorm(Launch $launch, Course $course, Activity $activity): ?array
    {
        $origin = $this->content->origin();

        if (!$launch->scorm instanceof ScormLaunch || null === $origin || null === $launch->url || !str_starts_with($launch->url, $origin.'/')) {
            return null;
        }

        $state = $launch->scorm->state(substr($launch->url, \strlen($origin))) + ['title' => $activity->title];

        return [
            'frame' => $this->content->runtime('frame.html'),
            'origin' => $origin,
            'state' => json_encode($state, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'commit' => $this->urls->generate(ScormController::COMMIT_ROUTE),
            'launch' => $this->urls->generate(self::LAUNCH_ROUTE, ['course' => $course->id->value, 'activity' => '00000000-0000-0000-0000-000000000000']),
            'outline' => $this->urls->generate(self::OUTLINE_ROUTE, ['course' => $course->id->value]),
            'number' => $launch->scorm->number,
            'resumed' => $launch->scorm->resumed,
            'review' => $launch->scorm->review,
        ];
    }

    public function complete(Request $request, string $course, string $activity): RedirectResponse
    {
        $this->trust($request);
        [$found, $enrollment, $activityId] = $this->enrolled($course, $activity);

        try {
            $this->learning->complete($enrollment, $activityId);
        } catch (ActivityUnavailable $activityUnavailable) {
            throw new BadRequestHttpException($activityUnavailable->getMessage(), $activityUnavailable);
        }

        return $this->back($found->id);
    }

    public function retry(Request $request, string $course, string $activity): RedirectResponse
    {
        $this->trust($request);
        [$found, $enrollment, $activityId] = $this->enrolled($course, $activity);

        try {
            $this->secondChances->request($enrollment, $activityId, $request->request->getString('reason'));
        } catch (ActivityUnavailable|InvalidCourse $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return $this->back($found->id);
    }

    /**
     * @return array{0: Course, 1: Enrollment, 2: ActivityId}
     */
    private function enrolled(string $course, string $activity): array
    {
        $learner = $this->learner();
        $found = $this->course($course);
        $enrollment = $this->enrollments->of($found->id, $learner) ?? throw new AccessDeniedException('Enroll in the course to take its activities.');

        try {
            $id = ActivityId::of($activity);
            $this->availabilities->of($enrollment, $id);
        } catch (CoursesException $coursesException) {
            throw new NotFoundHttpException('No such activity in this course.', $coursesException);
        }

        return [$found, $enrollment, $id];
    }

    private function course(string $course): Course
    {
        try {
            return $this->courses->get(CourseId::of($course));
        } catch (CoursesException $coursesException) {
            throw new NotFoundHttpException('No such course.', $coursesException);
        }
    }

    /**
     * @return list<array{course: Course, language: string}> the other courses of the course's group, by language
     */
    private function equivalents(CourseId $course): array
    {
        if (!$this->equivalences instanceof Equivalences) {
            return [];
        }

        $member = $this->equivalences->membership($course);

        if (!$member instanceof EquivalentCourse) {
            return [];
        }

        $equivalents = [];

        foreach ($this->equivalences->courses($member->group) as $other) {
            if (!$other->course->equals($course)) {
                $equivalents[] = ['course' => $this->courses->get($other->course), 'language' => $other->language];
            }
        }

        return $equivalents;
    }

    private function learner(): LearnerId
    {
        return $this->current->current() ?? throw new AccessDeniedException('Sign in to take the courses.');
    }

    private function trust(Request $request): void
    {
        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }
    }

    private function back(CourseId $course): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::OUTLINE_ROUTE, ['course' => $course->value]), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => $this->layout,
            'token' => $this->request->token(),
            'manual' => false,
            'scorm' => null,
        ]), $status, ['Cache-Control' => 'no-store']);
    }
}
