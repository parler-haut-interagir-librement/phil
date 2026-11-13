<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Http;

use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Application\Exception\CommentRefused;
use Phil\CommentsBundle\Application\Exception\NotFound;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The comments on a page of a browser without JavaScript: each plain form of the section posts here what it does —
 * `open`, `reply`, `edit`, `delete`, `resolve` or `reopen` —, the thread or comment it acts on, and the text, then comes
 * back to its page; or stays on a page that says what stopped it, the text kept to be copied. Every rule of the JSON
 * endpoints holds: the author the application names, its policy, the CSRF check.
 */
final readonly class PageController
{
    public const string DOMAIN = 'phil_comments';

    public function __construct(
        private Comments $comments,
        private PageForm $form,
        private Environment $twig,
        private TranslatorInterface $translator,
        private ?AuthorProvider $authors = null,
        private bool $enabled = false,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled || !$this->authors instanceof AuthorProvider) {
            return $this->stopped('page.stopped.disabled', '/', '', Response::HTTP_NOT_FOUND);
        }

        if (!$this->form->isSigned($request)) {
            return $this->stopped('page.stopped.forbidden', '/', '', Response::HTTP_FORBIDDEN);
        }

        $back = $request->query->getString('back');
        $body = $request->request->getString('body');

        if (!$this->form->isTrusted($request->request->getString('_token'))) {
            return $this->stopped('page.stopped.forbidden', $back, $body, Response::HTTP_FORBIDDEN);
        }

        $author = $this->authors->author();

        if (!$author instanceof ResourceReference) {
            return $this->stopped('page.stopped.anonymous', $back, $body, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->run($request, $author, $body);
        } catch (InvalidComment|InvalidResource) {
            return $this->stopped('page.stopped.invalid', $back, $body, Response::HTTP_BAD_REQUEST);
        } catch (CommentRefused) {
            return $this->stopped('page.stopped.refused', $back, $body, Response::HTTP_FORBIDDEN);
        } catch (NotFound) {
            return $this->stopped('page.stopped.missing', $back, $body, Response::HTTP_NOT_FOUND);
        }

        return new RedirectResponse($back.'#'.PageForm::SECTION, Response::HTTP_SEE_OTHER);
    }

    /**
     * @throws InvalidComment  when the action is unknown, or the text cannot be kept
     * @throws InvalidResource when the page is not a resource reference
     * @throws CommentRefused  when the policy refuses the action
     * @throws NotFound        when the thread or the comment is gone
     */
    private function run(Request $request, ResourceReference $author, string $body): void
    {
        $id = $request->request->getString('id');

        match ($request->request->getString('do')) {
            'open' => $this->comments->openOnPage(ResourceReference::fromString($request->query->getString('subject')), $author, $body),
            'reply' => $this->comments->reply($id, $author, $body),
            'edit' => $this->comments->edit($id, $author, $body),
            'delete' => $this->comments->delete($id, $author),
            'resolve' => $this->comments->resolve($id, $author),
            'reopen' => $this->comments->reopen($id, $author),
            default => throw InvalidComment::because('A form of the page opens, replies, edits, deletes, resolves or reopens.'),
        };
    }

    private function stopped(string $message, string $back, string $body, int $status): Response
    {
        return new Response($this->twig->render('@PhilComments/stopped.html.twig', [
            'message' => $this->translator->trans($message, [], self::DOMAIN),
            'back' => $back,
            'body' => $body,
        ]), $status);
    }
}
