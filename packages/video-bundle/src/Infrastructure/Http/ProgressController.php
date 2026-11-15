<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\Http;

use JsonException;
use Phil\VideoBundle\Application\Exception\InvalidVideo;
use Phil\VideoBundle\Application\ViewingProgress;
use Phil\VideoBundle\Application\ViewingTracker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives the viewings the player sends with `navigator.sendBeacon()`: `{"video": "<id>", "parts": "0101…"}`, a
 * kilobyte at most, from a page of the site only — `Sec-Fetch-Site: same-origin`, or an `Origin` of the site where the
 * browser sends no `Sec-Fetch-Site`. A viewing is counted or dropped by the tracker; the answer is `204` either way, so
 * that it says nothing of the viewer's consent.
 */
final readonly class ProgressController
{
    public const int MAX_BYTES = 1_024;

    public function __construct(
        private ViewingTracker $tracker,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->isSameOrigin($request)) {
            return $this->answer(Response::HTTP_FORBIDDEN);
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BYTES) {
            return $this->answer(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $viewing = json_decode($body, true, 2, \JSON_THROW_ON_ERROR);

            if (!\is_array($viewing) || !\is_string($viewing['video'] ?? null) || !\is_string($viewing['parts'] ?? null)) {
                return $this->answer(Response::HTTP_BAD_REQUEST);
            }

            $this->tracker->record(ViewingProgress::fromString($viewing['video'], $viewing['parts']));
        } catch (JsonException|InvalidVideo) {
            return $this->answer(Response::HTTP_BAD_REQUEST);
        }

        return $this->answer(Response::HTTP_NO_CONTENT);
    }

    private function isSameOrigin(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        if (null !== $site) {
            return 'same-origin' === $site;
        }

        return $request->headers->get('Origin') === $request->getSchemeAndHttpHost();
    }

    private function answer(int $status): Response
    {
        $response = new Response(null, $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
