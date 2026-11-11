<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Http;

use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the editor's `@` and `[[` menus: the letters typed after the trigger, in `q`, given to the application's
 * search for that menu, and its first suggestions as `{"items": [{"reference", "label", "url", "hint"}]}`. The current
 * user must be granted the editor's access attribute: the search is run for no one else.
 */
final readonly class ReferenceSearchController
{
    public const int LIMIT = 8;

    public const int MAX_QUERY = 100;

    public function __construct(
        private EditorRequest $editor,
        private ?ReferenceSearch $mentions = null,
        private ?ReferenceSearch $links = null,
    ) {
    }

    public function mentions(Request $request): JsonResponse
    {
        return $this->search($this->mentions, $request);
    }

    public function links(Request $request): JsonResponse
    {
        return $this->search($this->links, $request);
    }

    private function search(?ReferenceSearch $search, Request $request): JsonResponse
    {
        if (!$search instanceof ReferenceSearch) {
            return new JsonResponse(['items' => []], Response::HTTP_NOT_FOUND);
        }

        if (!$this->editor->isGranted() || !$this->editor->isTrusted($request)) {
            return new JsonResponse(['items' => []], Response::HTTP_FORBIDDEN);
        }

        $items = [];

        foreach ($search->search(mb_substr(trim($request->query->getString('q')), 0, self::MAX_QUERY), self::LIMIT) as $suggestion) {
            $items[] = $suggestion->toArray();

            if (self::LIMIT === \count($items)) {
                break;
            }
        }

        return new JsonResponse(['items' => $items]);
    }
}
