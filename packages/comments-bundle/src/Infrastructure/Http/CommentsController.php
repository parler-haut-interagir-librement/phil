<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Http;

use Closure;
use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Application\Discussion;
use Phil\CommentsBundle\Application\Exception\CommentRefused;
use Phil\CommentsBundle\Application\Exception\NotFound;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints of the comments controller. Each answers only a request of the controller ({@see CommentsRequest}),
 * from an author the application names, when the application has configured its author provider and its policy.
 * Bodies are JSON: `{"subject", "anchor": {"block", "field", "start", "quote", "prefix", "suffix"}, "body",
 * "suggestion"}` to open a thread on a passage, `{"subject", "anchor": null, "body"}` to open one on the whole page,
 * `{"body"}` to reply or edit. A thread is answered as `{"thread": …}`, the list as `{"threads": […]}`.
 */
final readonly class CommentsController
{
    public function __construct(
        private Comments $comments,
        private DiscussionJson $json,
        private CommentsRequest $requests,
        private ?AuthorProvider $authors = null,
        private bool $enabled = false,
    ) {
    }

    public function threads(Request $request): Response
    {
        return $this->handle($request, fn (ResourceReference $author): Response => new JsonResponse(['threads' => array_map(
            fn (Discussion $discussion): array => $this->json->of($discussion, $author),
            $this->comments->discussions(ResourceReference::fromString($request->query->getString('subject')), $author),
        )]), false);
    }

    public function open(Request $request): Response
    {
        return $this->handle($request, function (ResourceReference $author, array $body): Response {
            $subject = $body['subject'] ?? null;
            $suggestion = $body['suggestion'] ?? null;

            if (!\is_string($subject) || (null !== $suggestion && !\is_string($suggestion))) {
                throw InvalidComment::because('A thread is opened on a subject, with a suggestion or none, as texts.');
            }

            $anchor = $body['anchor'] ?? null;
            $discussion = null === $anchor && null === $suggestion
                ? $this->comments->openOnPage(ResourceReference::fromString($subject), $author, $this->body($body))
                : $this->comments->open(ResourceReference::fromString($subject), $this->anchor($anchor), $author, $this->body($body), $suggestion);

            return $this->answer($discussion, $author, Response::HTTP_CREATED);
        });
    }

    public function reply(Request $request, string $thread): Response
    {
        return $this->handle($request, fn (ResourceReference $author, array $body): Response => $this->answer($this->comments->reply($thread, $author, $this->body($body)), $author, Response::HTTP_CREATED));
    }

    public function resolve(Request $request, string $thread): Response
    {
        return $this->handle($request, fn (ResourceReference $author): Response => $this->answer($this->comments->resolve($thread, $author), $author), false);
    }

    public function reopen(Request $request, string $thread): Response
    {
        return $this->handle($request, fn (ResourceReference $author): Response => $this->answer($this->comments->reopen($thread, $author), $author), false);
    }

    public function accept(Request $request, string $thread): Response
    {
        return $this->handle($request, fn (ResourceReference $author): Response => $this->answer($this->comments->accept($thread, $author), $author), false);
    }

    public function reject(Request $request, string $thread): Response
    {
        return $this->handle($request, fn (ResourceReference $author): Response => $this->answer($this->comments->reject($thread, $author), $author), false);
    }

    public function edit(Request $request, string $comment): Response
    {
        return $this->handle($request, fn (ResourceReference $author, array $body): Response => $this->answer($this->comments->edit($comment, $author, $this->body($body)), $author));
    }

    public function delete(Request $request, string $comment): Response
    {
        return $this->handle($request, function (ResourceReference $author) use ($comment): Response {
            $discussion = $this->comments->delete($comment, $author);

            return $discussion instanceof Discussion ? $this->answer($discussion, $author) : new JsonResponse(['thread' => null]);
        }, false);
    }

    private function answer(Discussion $discussion, ResourceReference $author, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['thread' => $this->json->of($discussion, $author)], $status);
    }

    /**
     * Runs an endpoint for the current author, its errors answered with their status.
     *
     * @param Closure(ResourceReference, array<array-key, mixed>): Response $endpoint
     */
    private function handle(Request $request, Closure $endpoint, bool $body = true): Response
    {
        if (!$this->enabled || !$this->authors instanceof AuthorProvider) {
            return $this->error(Response::HTTP_NOT_FOUND, 'disabled');
        }

        if (!$this->requests->isTrusted($request)) {
            return $this->error(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        $author = $this->authors->author();

        if (!$author instanceof ResourceReference) {
            return $this->error(Response::HTTP_UNAUTHORIZED, 'anonymous');
        }

        try {
            return $endpoint($author, $body ? $request->toArray() : []);
        } catch (JsonException|InvalidComment|InvalidResource $exception) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'invalid', ['message' => $exception->getMessage()]);
        } catch (CommentRefused) {
            return $this->error(Response::HTTP_FORBIDDEN, 'refused');
        } catch (NotFound) {
            return $this->error(Response::HTTP_NOT_FOUND, 'missing');
        }
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function body(array $body): string
    {
        $text = $body['body'] ?? null;

        if (!\is_string($text)) {
            throw InvalidComment::because('A comment is sent as the text "body".');
        }

        return $text;
    }

    private function anchor(mixed $anchor): Anchor
    {
        $anchor = \is_array($anchor) ? $anchor : [];
        $block = $anchor['block'] ?? null;
        $field = $anchor['field'] ?? null;
        $start = $anchor['start'] ?? null;
        $quote = $anchor['quote'] ?? null;
        $prefix = $anchor['prefix'] ?? '';
        $suffix = $anchor['suffix'] ?? '';

        if ((null !== $block && !\is_string($block)) || (null !== $field && !\is_string($field)) || !\is_int($start) || !\is_string($quote) || !\is_string($prefix) || !\is_string($suffix)) {
            throw InvalidComment::because('An anchor is {"block", "field", "start", "quote", "prefix", "suffix"}: texts or null, and a whole number.');
        }

        return Anchor::of($block, $field, $start, $quote, $prefix, $suffix);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function error(int $status, string $error, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => $error, ...$extra], $status);
    }
}
