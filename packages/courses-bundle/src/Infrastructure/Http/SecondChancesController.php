<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The administration page of the second chances, for the teachers who hold the configured role: the requests waiting
 * for a decision, the oldest first, each with the learner, the course, the activity, the tries failed and the reason,
 * and the form that grants it, refuses it or waives the activity, with a note. Every decision is posted with a CSRF
 * token and made in the name of the teacher signed in, as the CurrentLearner service names them. Without an
 * authorization checker, the page is refused to everyone.
 */
final readonly class SecondChancesController
{
    public const string LIST_ROUTE = 'phil_courses_admin_retries';

    public const string DECIDE_ROUTE = 'phil_courses_admin_retry_decide';

    public const array DECISIONS = ['grant', 'refuse', 'waive'];

    public function __construct(
        private SecondChances $secondChances,
        private CurrentLearner $current,
        private CourseRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ?AuthorizationCheckerInterface $authorization,
        private string $role,
        private string $layout,
    ) {
    }

    public function list(): Response
    {
        $this->allow();

        return new Response($this->twig->render('@PhilCourses/second_chances.html.twig', [
            'reviews' => $this->secondChances->review(),
            'decisions' => self::DECISIONS,
            'layout' => $this->layout,
            'token' => $this->request->token(),
        ]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    public function decide(Request $request, string $retry): RedirectResponse
    {
        $teacher = $this->allow();

        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }

        $decision = $request->request->getString('decision');
        $note = $request->request->getString('note');

        try {
            $id = RetryRequestId::of($retry);
            match ($decision) {
                'grant' => $this->secondChances->grant($id, $teacher, $note),
                'refuse' => $this->secondChances->refuse($id, $teacher, $note),
                'waive' => $this->secondChances->waive($id, $teacher, $note),
                default => throw new BadRequestHttpException('The decision is to grant, refuse or waive.'),
            };
        } catch (UnknownRecord $unknownRecord) {
            throw new NotFoundHttpException('No such request.', $unknownRecord);
        } catch (CoursesException $coursesException) {
            throw new BadRequestHttpException($coursesException->getMessage(), $coursesException);
        }

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE), Response::HTTP_SEE_OTHER);
    }

    /**
     * @return string the stable identifier of the teacher signed in
     */
    private function allow(): string
    {
        $teacher = $this->current->current();

        if (!$teacher instanceof LearnerId || !$this->authorization instanceof AuthorizationCheckerInterface || !$this->authorization->isGranted($this->role)) {
            throw new AccessDeniedException('The second chances are decided by the teachers.');
        }

        return $teacher->value;
    }
}
