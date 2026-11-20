<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\Transcript;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The pages of the mandatory training: a learner's assigned courses, each with its due date and where they stand —
 * overdue included —, and their transcript, as a page, as JSON and as CSV; for the managers who hold the configured
 * role, the obligations overdue or due soon of every learner, and the transcript of any learner. Every answer is
 * `no-store`; the CSV is an attachment. Without an authorization checker, the managers' pages are refused to everyone.
 */
final readonly class TrainingController
{
    public const string MINE_ROUTE = 'phil_courses_training';

    public const string TRANSCRIPT_ROUTE = 'phil_courses_transcript';

    public const string TEAM_ROUTE = 'phil_courses_admin_training';

    public const string LEARNER_TRANSCRIPT_ROUTE = 'phil_courses_admin_transcript';

    public const array FORMATS = ['html', 'json', 'csv'];

    public function __construct(
        private Assignments $assignments,
        private Transcripts $transcripts,
        private CurrentLearner $current,
        private Environment $twig,
        private ?AuthorizationCheckerInterface $authorization,
        private string $role,
        private string $layout,
    ) {
    }

    public function mine(): Response
    {
        $learner = $this->learner();

        return $this->render('@PhilCourses/training.html.twig', ['statuses' => $this->assignments->of($learner)]);
    }

    public function transcript(string $_format = 'html'): Response
    {
        return $this->answer($this->transcripts->of($this->learner()), $_format, false);
    }

    public function team(): Response
    {
        $this->allow();

        return $this->render('@PhilCourses/training_admin.html.twig', ['statuses' => $this->assignments->attention()]);
    }

    public function learnerTranscript(string $learner, string $_format = 'html'): Response
    {
        $this->allow();

        try {
            $id = LearnerId::of($learner);
        } catch (CoursesException $coursesException) {
            throw new NotFoundHttpException('No such learner.', $coursesException);
        }

        return $this->answer($this->transcripts->of($id), $_format, true);
    }

    private function answer(Transcript $transcript, string $format, bool $manager): Response
    {
        return match ($format) {
            'json' => new JsonResponse($transcript->toArray(), Response::HTTP_OK, ['Cache-Control' => 'no-store']),
            'csv' => new Response($transcript->csv(), Response::HTTP_OK, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => \sprintf('attachment; filename="transcript-%s.csv"', $transcript->learner->value),
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]),
            default => $this->render('@PhilCourses/transcript.html.twig', [
                'transcript' => $transcript,
                'totals' => array_filter(array_map(static fn (CreditUnit $unit): ?array => 0 === $transcript->total($unit) ? null : ['unit' => $unit->value, 'amount' => Credits::write($transcript->total($unit))], CreditUnit::cases())),
                'manager' => $manager,
            ]),
        };
    }

    private function learner(): LearnerId
    {
        return $this->current->current() ?? throw new AccessDeniedException('Sign in to see your training.');
    }

    private function allow(): void
    {
        if (!$this->current->current() instanceof LearnerId || !$this->authorization instanceof AuthorizationCheckerInterface || !$this->authorization->isGranted($this->role)) {
            throw new AccessDeniedException('The training of the learners is for the managers.');
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context + ['layout' => $this->layout]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
