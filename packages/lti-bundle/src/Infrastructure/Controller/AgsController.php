<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Platform\Ags\AgsService;
use Phil\Lti\Platform\Ags\StoredLineItem;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Ags\MediaType;
use Phil\Lti\Service\Ags\Result;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Application\Event\LineItemDeleted;
use Phil\LtiBundle\Application\Event\LineItemSaved;
use Phil\LtiBundle\Application\Event\ScoreReceived;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Assignment and Grade Services of the platform over HTTP: the line items of a context — listed with their filters
 * a page at a time, the next page in the `Link` header with the limit the page was cut at, read, created, changed,
 * deleted —, the scores sent to a line item, and its results. Each call carries a
 * bearer token of the scope it needs; a context or a line item that is not the tool's answers 404. Every change is
 * told to the application by an event.
 */
final readonly class AgsController
{
    public const string LINE_ITEMS_ROUTE = 'phil_lti_platform_line_items';

    public const string LINE_ITEM_ROUTE = 'phil_lti_platform_line_item';

    public const string SCORES_ROUTE = 'phil_lti_platform_scores';

    public const string RESULTS_ROUTE = 'phil_lti_platform_results';

    public function __construct(
        private ServiceGuard $guard,
        private AgsService $ags,
        private PlatformUrls $urls,
        private EventDispatcherInterface $events,
    ) {
    }

    public function lineItems(Request $request, string $context): Response
    {
        try {
            $token = $this->guard->authorize($request->headers->get('Authorization'), [Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY]);

            if ($request->isMethod('POST')) {
                return $this->create($request, $token, $context);
            }

            $filters = ['resource_link_id' => ServiceResponses::stringQuery($request, 'resource_link_id'), 'resource_id' => ServiceResponses::stringQuery($request, 'resource_id'), 'tag' => ServiceResponses::stringQuery($request, 'tag')];
            $page = $this->ags->lineItems($token, $context, $filters['resource_link_id'], $filters['resource_id'], $filters['tag'], ServiceResponses::intQuery($request, 'limit'), ServiceResponses::intQuery($request, 'offset') ?? 0);
        } catch (AccessDenied $accessDenied) {
            return ServiceResponses::denied($accessDenied);
        }

        if (null === $page) {
            return ServiceResponses::notFound();
        }

        $headers = [];

        if (null !== $page['nextOffset']) {
            $query = array_filter($filters + ['limit' => $page['limit'], 'offset' => $page['nextOffset']], static fn (string|int|null $value): bool => null !== $value);
            $headers['Link'] = '<'.$this->urls->url(self::LINE_ITEMS_ROUTE, ['context' => $context] + $query)->toString().'>; rel="next"';
        }

        return ServiceResponses::json(array_map($this->present(...), $page['lineItems']), MediaType::LINE_ITEM_CONTAINER, headers: $headers);
    }

    public function lineItem(Request $request, string $context, string $id): Response
    {
        try {
            if ($request->isMethod('GET')) {
                $token = $this->guard->authorize($request->headers->get('Authorization'), [Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY]);
                $item = $this->ags->lineItem($token, $context, $id);

                return $item instanceof StoredLineItem ? ServiceResponses::json($this->present($item), MediaType::LINE_ITEM) : ServiceResponses::notFound();
            }

            $token = $this->guard->authorize($request->headers->get('Authorization'), [Scope::LINE_ITEM]);

            if ($request->isMethod('DELETE')) {
                $item = $this->ags->lineItem($token, $context, $id);

                if (!$item instanceof StoredLineItem || !$this->ags->delete($token, $context, $id)) {
                    return ServiceResponses::notFound();
                }

                $this->events->dispatch(new LineItemDeleted($item));

                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $lineItem = $this->read($request);

            if (!$lineItem instanceof LineItem) {
                return ServiceResponses::error('The body is not a line item: '.$lineItem);
            }

            $item = $this->ags->update($token, $context, $id, $lineItem);
        } catch (AccessDenied $accessDenied) {
            return ServiceResponses::denied($accessDenied);
        }

        if (!$item instanceof StoredLineItem) {
            return ServiceResponses::notFound();
        }

        $this->events->dispatch(new LineItemSaved($item, false));

        return ServiceResponses::json($this->present($item), MediaType::LINE_ITEM);
    }

    public function scores(Request $request, string $context, string $id): Response
    {
        try {
            $token = $this->guard->authorize($request->headers->get('Authorization'), [Scope::SCORE]);
            $body = ServiceResponses::body($request);

            try {
                $score = Score::fromArray($body ?? throw InvalidData::because('The body is not a JSON object.'));
            } catch (InvalidData $invalidData) {
                return ServiceResponses::error('The body is not a score: '.$invalidData->getMessage());
            }

            $kept = $this->ags->score($token, $context, $id, $score);
        } catch (AccessDenied $accessDenied) {
            return ServiceResponses::denied($accessDenied);
        }

        if (null === $kept) {
            return ServiceResponses::notFound();
        }

        if (!$kept) {
            return ServiceResponses::error('The score is older than the latest kept for the learner.', Response::HTTP_CONFLICT);
        }

        $item = $this->ags->lineItem($token, $context, $id);

        if ($item instanceof StoredLineItem) {
            $this->events->dispatch(new ScoreReceived($item, $score));
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function results(Request $request, string $context, string $id): Response
    {
        try {
            $token = $this->guard->authorize($request->headers->get('Authorization'), [Scope::RESULT_READ_ONLY]);
            $results = $this->ags->results($token, $context, $id, ServiceResponses::stringQuery($request, 'user_id'), ServiceResponses::intQuery($request, 'limit'), ServiceResponses::intQuery($request, 'offset') ?? 0);
        } catch (AccessDenied $accessDenied) {
            return ServiceResponses::denied($accessDenied);
        }

        if (null === $results) {
            return ServiceResponses::notFound();
        }

        $lineItem = $this->urls->lineItem($context, $id);

        return ServiceResponses::json(array_map(fn (Result $result): array => new Result(
            $result->userId,
            $result->resultScore,
            $result->resultMaximum,
            $result->comment,
            $this->urls->url(self::RESULTS_ROUTE, ['context' => $context, 'id' => $id, 'user_id' => $result->userId]),
            $lineItem,
        )->toArray(), $results), MediaType::RESULT_CONTAINER);
    }

    private function create(Request $request, AccessToken $token, string $context): Response
    {
        $lineItem = $this->read($request);

        if (!$lineItem instanceof LineItem) {
            return ServiceResponses::error('The body is not a line item: '.$lineItem);
        }

        $item = $this->ags->create($token, $context, $lineItem);

        if (!$item instanceof StoredLineItem) {
            return ServiceResponses::notFound();
        }

        $this->events->dispatch(new LineItemSaved($item, true));

        return ServiceResponses::json($this->present($item), MediaType::LINE_ITEM, Response::HTTP_CREATED);
    }

    private function read(Request $request): LineItem|string
    {
        $body = ServiceResponses::body($request);

        if (null === $body) {
            return 'it is not a JSON object.';
        }

        unset($body['id']);

        try {
            return LineItem::fromArray($body);
        } catch (InvalidData $invalidData) {
            return $invalidData->getMessage();
        }
    }

    /**
     * @return array<string, string|float|bool>
     */
    private function present(StoredLineItem $item): array
    {
        return $item->lineItem->withId($this->urls->lineItem($item->contextId, $item->id))->toArray();
    }
}
