<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Domain\Exception\AttemptRefused;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint the page of a SCORM session posts its commits to, with `fetch()` or `navigator.sendBeacon()` when the
 * learner leaves: a JSON object of the attempt token, the stateless CSRF token of the courses, the elements the content
 * wrote since the last commit, in their order, and whether the session ends. The attempt token authenticates the
 * attempt; when a learner is signed in, it must be theirs.
 *
 * It answers the elements left out with their error codes, the token of the next commit, and after a navigation
 * request the launch of the next activity or the outline; `400` for a body that is not such an object, `403` for a
 * token or a CSRF token refused, `409` for a session that is over, `413` for a body past `MAX_BYTES`.
 */
final readonly class ScormController
{
    public const string COMMIT_ROUTE = 'phil_courses_scorm_commit';

    public const int MAX_BYTES = 2_097_152;

    public function __construct(
        private ScormRuntime $runtime,
        private CourseRequest $request,
        private CurrentLearner $current,
    ) {
    }

    public function commit(Request $request): JsonResponse
    {
        $length = $request->headers->get('Content-Length');

        if (null !== $length && (int) $length > self::MAX_BYTES) {
            return $this->error('The commit is too large.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BYTES) {
            return $this->error('The commit is too large.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $payload = ScormCommitPayload::fromJson($body);

        if (!$payload instanceof ScormCommitPayload) {
            return $this->error('The commit is not a JSON object of a token, a CSRF token, values and whether the session ends.', Response::HTTP_BAD_REQUEST);
        }

        if (!$this->request->isTrustedToken($request, $payload->csrf)) {
            return $this->error('The page expired: load it again.', Response::HTTP_FORBIDDEN);
        }

        try {
            $answer = $this->runtime->commit($payload->token, $payload->values, $payload->finish, $this->current->current());
        } catch (AttemptRefused $attemptRefused) {
            return $this->error($attemptRefused->getMessage(), $attemptRefused->closed ? Response::HTTP_CONFLICT : Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse([
            'errors' => (object) $answer->errors,
            'token' => $answer->token,
            'ended' => $answer->ended,
            'next' => $answer->next?->value,
            'exit' => $answer->exit,
        ], Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status, ['Cache-Control' => 'no-store']);
    }
}
