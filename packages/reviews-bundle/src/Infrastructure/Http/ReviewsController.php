<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Http;

use Phil\ReviewsBundle\Application\Contract\CurrentReviewer;
use Phil\ReviewsBundle\Application\Contract\SubjectDirectory;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The page of a subject's reviews and its forms, which work without JavaScript: the summary of its ratings and
 * superlatives, the published reviews in the order chosen, the member's form to write or change their review, the
 * votes of helpfulness, the responses of the merchant or the organiser, and the contest of a withdrawal. Every form
 * passes the stateless CSRF check; the review form passes phil/spam-protection-bundle when it is installed.
 */
final readonly class ReviewsController
{
    public const string PAGE_ROUTE = 'phil_reviews_page';

    public const string WRITE_ROUTE = 'phil_reviews_write';

    public const string VOTE_ROUTE = 'phil_reviews_vote';

    public const string RESPOND_ROUTE = 'phil_reviews_respond';

    public const string CONTEST_ROUTE = 'phil_reviews_contest';

    public const string TYPE = '[a-z][a-z0-9_.-]{0,63}';

    public const string SUBJECT = '[^/]{1,190}';

    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private const array NO_STORE = ['Cache-Control' => 'no-store, private'];

    /**
     * @param positive-int $pageSize
     */
    public function __construct(
        private Reviews $reviews,
        private Ratings $ratings,
        private SubjectTypes $types,
        private RemovalCriteria $criteria,
        private CurrentReviewer $current,
        private SubjectDirectory $subjects,
        private ReviewsRequest $request,
        private Environment $twig,
        private UrlGeneratorInterface $router,
        private string $layout,
        private int $pageSize,
        private ?SpamGuard $spam = null,
    ) {
    }

    public function page(Request $request, string $type, string $id): Response
    {
        return $this->render($request, $this->subject($type, $id));
    }

    public function write(Request $request, string $type, string $id): Response
    {
        $subject = $this->subject($type, $id);
        $member = $this->current->member();

        if (!$member instanceof MemberId || !$this->request->isTrusted($request)) {
            return $this->render($request, $subject, 'error.expired', Response::HTTP_FORBIDDEN);
        }

        $title = $request->request->getString('title');
        $body = $request->request->getString('body');

        if ($this->spam instanceof SpamGuard && $this->spam->refuses($request, [$title, $body])) {
            return $this->render($request, $subject, 'error.rejected', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $content = new ReviewContent(
                $request->request->getInt('overall'),
                $this->scores($request->request->all('criteria')),
                $title,
                $body,
                array_values(array_filter($request->request->all('superlatives'), \is_string(...))),
            );
            $compensated = $request->request->getBoolean('compensated');
            $compensation = Compensation::of($compensated, $compensated ? $request->request->getString('compensation_note') : null);
            $mine = $this->reviews->mineOf($subject, $member);
            $review = $mine instanceof Review
                ? $this->reviews->edit($mine->id, $member, $content, $compensation)
                : $this->reviews->write($subject, $member, $content, $compensation);
        } catch (InvalidReview|ReviewRefused $refused) {
            return $this->render($request, $subject, $refused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($review);
    }

    public function vote(Request $request, string $review): Response
    {
        $found = $this->review($review);
        $member = $this->current->member();

        if (!$member instanceof MemberId || !$this->request->isTrusted($request)) {
            return $this->refuse($request, $found, 'error.expired', Response::HTTP_FORBIDDEN);
        }

        try {
            match ($request->request->getString('vote')) {
                'helpful' => $this->reviews->vote($found->id, $member, true),
                'unhelpful' => $this->reviews->vote($found->id, $member, false),
                default => $this->reviews->retractVote($found->id, $member),
            };
        } catch (ReviewRefused $reviewRefused) {
            return $this->refuse($request, $found, $reviewRefused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->wantsJson($request)) {
            $counted = $this->reviews->get($found->id) ?? $found;

            return new JsonResponse(['helpful' => $counted->helpful(), 'unhelpful' => $counted->unhelpful(), 'vote' => $this->reviews->voteOf($found->id, $member)], headers: self::NO_STORE);
        }

        return $this->back($found);
    }

    public function respond(Request $request, string $review): Response
    {
        $found = $this->review($review);
        $member = $this->current->member();

        if (!$member instanceof MemberId || !$this->request->isTrusted($request)) {
            return $this->refuse($request, $found, 'error.expired', Response::HTTP_FORBIDDEN);
        }

        $text = $request->request->getString('response');

        try {
            $answered = '' === trim($text) ? $this->reviews->removeResponse($found->id, $member) : $this->reviews->respond($found->id, $member, $text);
        } catch (InvalidReview|ReviewRefused $refused) {
            return $this->refuse($request, $found, $refused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($answered);
    }

    public function contest(Request $request, string $review): Response
    {
        $found = $this->review($review);
        $member = $this->current->member();

        if (!$member instanceof MemberId || !$this->request->isTrusted($request)) {
            return $this->refuse($request, $found, 'error.expired', Response::HTTP_FORBIDDEN);
        }

        try {
            $contested = $this->reviews->contest($found->id, $member, $request->request->getString('evidence'));
        } catch (InvalidReview|ReviewRefused $refused) {
            return $this->refuse($request, $found, $refused->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($contested);
    }

    /**
     * @param array<array-key, mixed> $submitted
     *
     * @return array<array-key, mixed> the scores given, a criterion left unrated left out
     */
    private function scores(array $submitted): array
    {
        $scores = [];

        foreach ($submitted as $criterion => $score) {
            if (\is_string($score) && 1 === preg_match('/^[1-9][0-9]?$/D', $score)) {
                $scores[$criterion] = (int) $score;
            } elseif (!\in_array($score, ['', '0'], true)) {
                $scores[$criterion] = $score;
            }
        }

        return $scores;
    }

    private function wantsJson(Request $request): bool
    {
        return 'json' === $request->getPreferredFormat();
    }

    private function subject(string $type, string $id): SubjectRef
    {
        try {
            $subject = SubjectRef::of($type, $id);
        } catch (InvalidReview $invalidReview) {
            throw new NotFoundHttpException('No such subject.', $invalidReview);
        }

        if (!$this->types->has($subject->type)) {
            throw new NotFoundHttpException('No such subject.');
        }

        return $subject;
    }

    private function review(string $id): Review
    {
        try {
            $review = $this->reviews->get(ReviewId::fromString($id));
        } catch (InvalidReview $invalidReview) {
            throw new NotFoundHttpException('No such review.', $invalidReview);
        }

        return $review ?? throw new NotFoundHttpException('No such review.');
    }

    private function refuse(Request $request, Review $review, string $error, int $status): Response
    {
        if ($this->wantsJson($request)) {
            return new JsonResponse(['error' => $error], $status, self::NO_STORE);
        }

        return $this->render($request, $review->subject, $error, $status);
    }

    private function back(Review $review): RedirectResponse
    {
        return new RedirectResponse(
            $this->router->generate(self::PAGE_ROUTE, ['type' => $review->subject->type, 'id' => $review->subject->id]).'#review-'.$review->id->value,
            Response::HTTP_SEE_OTHER,
        );
    }

    private function render(Request $request, SubjectRef $subject, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $type = $this->types->get($subject->type);
        $order = ReviewOrder::tryFrom($request->query->getString('order')) ?? ReviewOrder::Helpful;
        $count = $this->reviews->count($subject);
        $pages = max(1, (int) ceil($count / $this->pageSize));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));
        $reviews = $this->reviews->of($subject, $order, $this->pageSize, ($page - 1) * $this->pageSize);
        $member = $this->current->member();
        $votes = [];

        if ($member instanceof MemberId) {
            foreach ($reviews as $review) {
                $votes[$review->id->value] = $this->reviews->voteOf($review->id, $member);
            }
        }

        $summary = $this->ratings->summary($subject);

        return new Response($this->twig->render('@PhilReviews/page.html.twig', [
            'layout' => $this->layout,
            'subject' => $subject,
            'subject_title' => $this->subjects->title($subject),
            'subject_url' => $this->subjects->url($subject),
            'type' => $type,
            'summary' => $summary,
            'superlatives' => $summary->topSuperlatives($type->superlatives, $type->superlativesShown),
            'reviews' => $reviews,
            'order' => $order,
            'orders' => ReviewOrder::cases(),
            'page' => $page,
            'pages' => $pages,
            'me' => $member,
            'mine' => $member instanceof MemberId ? $this->reviews->mineOf($subject, $member) : null,
            'refusal' => $member instanceof MemberId ? $this->reviews->refusal($subject, $member) : null,
            'may_respond' => $this->reviews->mayRespond($subject, $member),
            'votes' => $votes,
            'criteria' => $this->criteria->all(),
            'token' => $this->request->token(),
            'spam' => $member instanceof MemberId ? $this->spam?->protect() : null,
            'values' => null === $error ? [] : $request->request->all(),
            'error' => $error,
        ]), $status, self::NO_STORE);
    }
}
