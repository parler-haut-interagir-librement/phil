<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use LogicException;
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Application\Contract\CourseCheckout;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Exception\EnrollmentRefused;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The pages of the catalogues: the catalogues shown to the visitor — the public ones, and those of the units of a
 * learner signed in —, one catalogue a page at a time with its filters, its order and where the learner stands in each
 * course, and the form that enrolls the learner in a free course open to self-enrollment, or sends them to pay for a
 * course sold through the application's `CourseCheckout`, at an address of the site itself. A catalogue not shown to the visitor answers 404; every
 * change is posted with a CSRF token.
 */
final readonly class CatalogueController
{
    public const string INDEX_ROUTE = 'phil_courses_catalogues';

    public const string SHOW_ROUTE = 'phil_courses_catalogue';

    public const string ENROLL_ROUTE = 'phil_courses_catalogue_enroll';

    public function __construct(
        private Catalogues $catalogues,
        private CurrentLearner $current,
        private CourseRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private ?CourseCheckout $checkout = null,
    ) {
    }

    public function index(): Response
    {
        return $this->render('@PhilCourses/catalogues.html.twig', ['catalogues' => $this->catalogues->shownTo($this->current->current())]);
    }

    public function show(Request $request, string $catalogue): Response
    {
        try {
            $page = $this->catalogues->browse(CatalogueId::of($catalogue), $this->current->current(), CatalogueQuery::fromQuery($request->query->all(), $request->getLocale()));
        } catch (CoursesException $coursesException) {
            throw new NotFoundHttpException('No such catalogue.', $coursesException);
        }

        return $this->render('@PhilCourses/catalogue.html.twig', [
            'page' => $page,
            'signed_in' => $this->current->current() instanceof LearnerId,
            'checkout' => $this->checkout instanceof CourseCheckout,
        ]);
    }

    /**
     * Enrolls the learner in a free course open to self-enrollment, or sends them to pay for a course sold.
     */
    public function enroll(Request $request, string $catalogue, string $course): RedirectResponse
    {
        if (!$this->request->isTrusted($request)) {
            throw new BadRequestHttpException('The form expired: load the page again.');
        }

        $learner = $this->current->current() ?? throw new AccessDeniedException('Sign in to enroll in a course.');

        try {
            $catalogueId = CatalogueId::of($catalogue);
            $courseId = CourseId::of($course);
            $this->catalogues->shown($catalogueId, $learner);
        } catch (CoursesException $coursesException) {
            throw new NotFoundHttpException('No such catalogue.', $coursesException);
        }

        try {
            $this->catalogues->enroll($catalogueId, $courseId, $learner);
        } catch (EnrollmentRefused $enrollmentRefused) {
            $entry = $this->catalogues->entry($catalogueId, $courseId);

            if ($enrollmentRefused->purchase && $entry instanceof CatalogueEntry && $this->checkout instanceof CourseCheckout) {
                return new RedirectResponse($this->local($request, $this->checkout->checkout($entry, $learner)), Response::HTTP_SEE_OTHER);
            }

            throw new ConflictHttpException($enrollmentRefused->getMessage(), $enrollmentRefused);
        }

        return new RedirectResponse($this->urls->generate(CourseController::OUTLINE_ROUTE, ['course' => $courseId->value]), Response::HTTP_SEE_OTHER);
    }

    /**
     * The address of the checkout, when it is on the site itself: a path, or an address of the request's origin.
     *
     * @throws LogicException when the checkout names another site
     */
    private function local(Request $request, string $address): string
    {
        $path = str_starts_with($address, '/') && !str_starts_with($address, '//') && !str_starts_with($address, '/\\');

        if ($path || str_starts_with($address, $request->getSchemeAndHttpHost().'/')) {
            return $address;
        }

        throw new LogicException('The CourseCheckout answers an address of the site itself.');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => $this->layout,
            'token' => $this->request->token(),
        ]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
