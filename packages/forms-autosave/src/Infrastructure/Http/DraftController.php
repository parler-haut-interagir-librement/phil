<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Http;

use DateTimeInterface;
use JsonException;
use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Phil\FormsAutosave\Application\DraftCopy;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\Exception\DraftTooLarge;
use Phil\FormsAutosave\Application\Exception\RevisionConflict;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints of the server's drafts, one path per key: `GET` answers `{"revision", "savedAt", "version", "fields"}`
 * or `204` when none is kept; `PUT` takes `{"revision", "version", "fields"}`, the revision the browser read or null,
 * and answers `{"revision"}`, or `409` with the revision kept when another was saved meanwhile; `DELETE` answers `204`.
 * Each answers only a request of the controller, from a signed-in user — `403` otherwise —, and never caches.
 */
final readonly class DraftController
{
    public function __construct(
        private Drafts $drafts,
        private DraftRequest $requests,
        private DraftOwner $owner,
        private int $maxBytes = Drafts::MAX_BYTES,
    ) {
    }

    public function read(Request $request, string $key): Response
    {
        return $this->handle($request, $key, function (string $user, DraftKey $draft): Response {
            $copy = $this->drafts->read($user, $draft);

            return $copy instanceof DraftCopy ? new JsonResponse([
                'revision' => $copy->revision,
                'savedAt' => $copy->savedAt->format(DateTimeInterface::ATOM),
                'version' => $copy->content->version,
                'fields' => (object) $copy->content->fields,
            ]) : new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    public function save(Request $request, string $key): Response
    {
        return $this->handle($request, $key, function (string $user, DraftKey $draft) use ($request): Response {
            $body = $request->getContent();

            if (\strlen($body) > 2 * $this->maxBytes) {
                throw DraftTooLarge::above($this->maxBytes);
            }

            try {
                $json = json_decode($body, true, 4, \JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw InvalidDraft::because('The body is a JSON object.');
            }

            if (!\is_array($json)) {
                throw InvalidDraft::because('The body is a JSON object.');
            }

            $revision = $json['revision'] ?? null;

            if (null !== $revision && !\is_int($revision)) {
                throw InvalidDraft::because('The revision read is a number or null.');
            }

            $saved = $this->drafts->save($user, $draft, $revision, DraftContent::of($json['version'] ?? null, $json['fields'] ?? null));

            return new JsonResponse(['revision' => $saved]);
        });
    }

    public function discard(Request $request, string $key): Response
    {
        return $this->handle($request, $key, function (string $user, DraftKey $draft): Response {
            $this->drafts->discard($user, $draft);

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * @param callable(string, DraftKey): Response $answer
     */
    private function handle(Request $request, string $key, callable $answer): Response
    {
        $user = $this->owner->current();

        if (null === $user || !$this->requests->isTrusted($request)) {
            return $this->private(new JsonResponse(['error' => 'This request is not allowed.'], Response::HTTP_FORBIDDEN));
        }

        try {
            return $this->private($answer($user, DraftKey::of($key)));
        } catch (RevisionConflict $conflict) {
            return $this->private(new JsonResponse(['error' => $conflict->getMessage(), 'revision' => $conflict->current], Response::HTTP_CONFLICT));
        } catch (DraftTooLarge $tooLarge) {
            return $this->private(new JsonResponse(['error' => $tooLarge->getMessage()], Response::HTTP_REQUEST_ENTITY_TOO_LARGE));
        } catch (InvalidDraft $invalid) {
            return $this->private(new JsonResponse(['error' => $invalid->getMessage()], Response::HTTP_BAD_REQUEST));
        }
    }

    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
