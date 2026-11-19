<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Http;

use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Exception\ReactionLimitReached;
use Phil\ReactionsBundle\Application\Exception\ReactionRefused;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Phil\ReactionsBundle\Application\Exception\UnknownReaction;
use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The reactions of a browser without JavaScript: a plain form posts here and comes back to its page, or stays on a page
 * that says what stopped the change. Every check of the component is made here too.
 */
final readonly class ReactionController
{
    public function __construct(
        private Reactions $reactions,
        private CurrentMember $member,
        private ReactionGuard $guard,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->guard->isSigned($request)) {
            return $this->stopped('reactions.refused', '/', Response::HTTP_FORBIDDEN);
        }

        $back = $request->query->getString('back');

        try {
            $this->guard->assertTrusted($request->request->getString('_token'));
            $member = $this->member->current() ?? throw ReactionRefused::noMember();
            $content = ResourceReference::fromString($request->query->getString('content'));

            if ($request->request->has('withdraw')) {
                $code = $request->request->getString('withdraw');
                $this->reactions->withdraw($content, $member, '' === $code ? null : $code);
            } else {
                $this->reactions->react($content, $member, $request->request->getString('react'));
            }

            return new RedirectResponse($back, Response::HTTP_SEE_OTHER);
        } catch (ReactionRefused) {
            return $this->stopped('reactions.refused', $back, Response::HTTP_FORBIDDEN);
        } catch (UnknownReaction) {
            return $this->stopped('reactions.unknown', $back, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ReactionLimitReached) {
            return $this->stopped('reactions.limit', $back, Response::HTTP_CONFLICT);
        } catch (TooManyReactions $tooManyReactions) {
            $response = $this->stopped('reactions.too_many', $back, Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $tooManyReactions->retryAfter);

            return $response;
        }
    }

    private function stopped(string $message, string $back, int $status): Response
    {
        return new Response($this->twig->render('@PhilReactions/stopped.html.twig', [
            'message' => $this->translator->trans($message, [], ReactionBoard::DOMAIN),
            'back' => $back,
        ]), $status);
    }
}
