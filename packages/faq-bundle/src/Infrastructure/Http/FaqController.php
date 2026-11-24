<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Http;

use Phil\FaqBundle\Application\Exception\AlreadyVoted;
use Phil\FaqBundle\Application\Exception\TooManyRequests;
use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\VoteRefused;
use Phil\FaqBundle\Application\FaqPages;
use Phil\FaqBundle\Application\HelpfulVotes;
use Phil\FaqBundle\Application\UnansweredSearches;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The page of questions, its votes and its searches without answer.
 *
 * The page filters the questions by the `q` of its address, so that searching works without JavaScript, and counts a
 * search that found nothing. A vote comes from a plain form, which comes back to its page, or from the Stimulus
 * controller, which asks for JSON. Nothing of the reader is read but what the form sends: no address, no cookie.
 */
final readonly class FaqController
{
    public const string PAGE_ROUTE = 'phil_faq_page';

    public const string VOTE_ROUTE = 'phil_faq_vote';

    public const string UNANSWERED_ROUTE = 'phil_faq_unanswered';

    public const string DOMAIN = 'phil_faq';

    public function __construct(
        private FaqPages $pages,
        private HelpfulVotes $votes,
        private UnansweredSearches $unanswered,
        private FaqGuard $guard,
        private Environment $twig,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private string $layout,
    ) {
    }

    public function page(Request $request): Response
    {
        $locale = $this->locale($request);
        $page = $this->pages->page($locale, $request->query->getString('q'));

        if ($page->hasNoResult()) {
            try {
                $this->unanswered->record($locale, $page->query);
            } catch (TooManyRequests) {
                // A burst of searches in one language: the page shows, the total waits.
            }
        }

        $response = new Response($this->twig->render('@PhilFaq/page.html.twig', ['layout' => $this->layout, 'page' => $page]));
        // The vote token of the page is its own: a shared cache would give every reader the same one.
        $response->setPrivate();

        return $response;
    }

    public function vote(Request $request, string $anchor): Response
    {
        $json = $this->wantsJson($request);
        $back = FaqGuard::localPath($request->request->getString('back'), $this->urls->generate(self::PAGE_ROUTE));
        $helpful = $request->request->getString('helpful');

        try {
            $this->guard->assertTrusted($request->request->getString('_token'));

            if (!\in_array($helpful, ['yes', 'no'], true)) {
                return $this->refused($json, 'invalid', $back, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $count = $this->votes->vote($anchor, 'yes' === $helpful, $request->request->getString('vote_token'));
        } catch (VoteRefused) {
            return $this->refused($json, 'refused', $back, Response::HTTP_FORBIDDEN);
        } catch (UnknownQuestion) {
            return $this->refused($json, 'unknown', $back, Response::HTTP_NOT_FOUND);
        } catch (AlreadyVoted) {
            return $this->refused($json, 'already', $back, Response::HTTP_CONFLICT);
        } catch (TooManyRequests $tooMany) {
            $response = $this->refused($json, 'too_many', $back, Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $tooMany->retryAfter);

            return $response;
        }

        return $json ? $this->counted($count) : new RedirectResponse(FaqGuard::withParameter($back, 'voted', $anchor, $anchor), Response::HTTP_SEE_OTHER);
    }

    public function unanswered(Request $request): Response
    {
        try {
            $this->guard->assertTrusted($request->request->getString('_token'));
            $this->unanswered->record($this->locale($request), $request->request->getString('q'));
        } catch (VoteRefused) {
            return new JsonResponse(['error' => 'refused'], Response::HTTP_FORBIDDEN);
        } catch (TooManyRequests $tooMany) {
            return new JsonResponse(['error' => 'too_many'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $tooMany->retryAfter]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The locale of the request, or English when the request names one the bundle cannot read.
     */
    private function locale(Request $request): Locale
    {
        try {
            return Locale::fromString($request->getLocale());
        } catch (InvalidFaq) {
            return Locale::fromString('en');
        }
    }

    private function wantsJson(Request $request): bool
    {
        return 'json' === $request->getPreferredFormat(null);
    }

    private function counted(VoteCount $count): JsonResponse
    {
        return new JsonResponse(['yes' => $count->yes, 'no' => $count->no]);
    }

    private function refused(bool $json, string $reason, string $back, int $status): Response
    {
        if ($json) {
            return new JsonResponse(['error' => $reason], $status);
        }

        return new Response($this->twig->render('@PhilFaq/stopped.html.twig', [
            'layout' => $this->layout,
            'message' => $this->translator->trans('faq.vote.'.$reason, [], self::DOMAIN),
            'back' => $back,
        ]), $status);
    }
}
