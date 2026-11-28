<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\Http;

use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Exception\RenderingFailed;
use Phil\Ujo\Block;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The drawing of one block while its author writes it: `POST {"type": "math", "data": {"latex": "x^2"}}` answers
 * `{"markup": svg or mathml}`, or `{"error": text}` with 422 when the source cannot be drawn. Nothing is kept: the
 * drawing a document keeps is made again, and signed, when it is saved.
 *
 * Answers 403 to a request that is not from this site, 413 above the size limit, 400 to a body that is not that
 * object and 404 for a type that is not drawn.
 */
final readonly class PreviewController
{
    public const int MAX_BYTES = 65_536;

    public function __construct(
        private BlockMarkup $markup,
        private PreviewRequest $guard,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->guard->isTrusted($request)) {
            return $this->answer(['error' => 'The preview is only drawn for the pages of this site.'], 403);
        }

        $body = $request->getContent();

        if (\strlen($body) > $this->maxBytes) {
            return $this->answer(['error' => \sprintf('The block is larger than %d bytes.', $this->maxBytes)], 413);
        }

        $payload = json_decode($body, true, 64);
        $type = \is_array($payload) ? $payload['type'] ?? null : null;
        $data = \is_array($payload) ? $payload['data'] ?? null : null;

        if (!\is_string($type) || !\is_array($data)) {
            return $this->answer(['error' => 'The body is {"type": text, "data": object}.'], 400);
        }

        if (!$this->markup->draws($type)) {
            return $this->answer(['error' => \sprintf('Blocks of type "%s" are not drawn.', mb_substr($type, 0, 64))], 404);
        }

        try {
            return $this->answer(['markup' => $this->markup->preview(new Block('preview', $type, $data))], 200);
        } catch (RenderingFailed $renderingFailed) {
            return $this->answer(['error' => $renderingFailed->getMessage()], 422);
        }
    }

    /**
     * @param array<string, string> $data
     */
    private function answer(array $data, int $status): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
