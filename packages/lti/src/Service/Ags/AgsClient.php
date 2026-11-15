<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\LinkHeader;
use Phil\Lti\Http\UrlPath;
use Phil\Lti\Http\UrlQuery;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Service\Scope;
use Phil\Lti\Service\ServiceCall;
use Phil\Url\Url;

/**
 * The Assignment and Grade Services of a platform, called by the tool with the endpoint claim of a launch: the line
 * items of the context, read, created, changed and deleted; the scores sent; the results read. Each call asks for the
 * scope it needs, when the claim grants it.
 */
final readonly class AgsClient
{
    public function __construct(
        private ServiceCall $call,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * A page of the line items of the context, filtered by resource link, resource or tag.
     *
     * @throws ServiceFailed|InvalidData|InvalidRequest
     */
    public function lineItems(PlatformRegistration $platform, AgsEndpoint $endpoint, ?string $resourceLinkId = null, ?string $resourceId = null, ?string $tag = null, ?int $limit = null, ?Url $page = null): LineItemPage
    {
        $url = $page ?? $this->container($endpoint);
        $query = array_filter(['resource_link_id' => $resourceLinkId, 'resource_id' => $resourceId, 'tag' => $tag, 'limit' => null === $limit ? null : (string) $limit], static fn (?string $value): bool => null !== $value);

        if (!$page instanceof Url && [] !== $query) {
            $url = UrlQuery::with($url, $query, $this->allowLocalhost);
        }

        $response = $this->call->send($platform, [$this->readScope($endpoint)], 'GET', $url, MediaType::LINE_ITEM_CONTAINER);
        $items = ServiceCall::json($response);

        if (!array_is_list($items)) {
            throw InvalidData::because('The line items of a context are a list.');
        }

        $lineItems = [];

        foreach ($items as $item) {
            $lineItems[] = LineItem::fromArray(\is_array($item) ? $item : [], $this->allowLocalhost);
        }

        return new LineItemPage($lineItems, LinkHeader::find($response->headers('link'), 'next', $url, $this->allowLocalhost));
    }

    /**
     * @throws ServiceFailed|InvalidData|InvalidRequest
     */
    public function lineItem(PlatformRegistration $platform, AgsEndpoint $endpoint, Url $lineItem): LineItem
    {
        return LineItem::fromArray(ServiceCall::json($this->call->send($platform, [$this->readScope($endpoint)], 'GET', $lineItem, MediaType::LINE_ITEM)), $this->allowLocalhost);
    }

    /**
     * @throws ServiceFailed|InvalidData|InvalidRequest
     */
    public function createLineItem(PlatformRegistration $platform, AgsEndpoint $endpoint, LineItem $lineItem): LineItem
    {
        $this->require($endpoint, Scope::LINE_ITEM);
        $body = $lineItem->toArray();
        unset($body['id']);

        return LineItem::fromArray(ServiceCall::json($this->call->send($platform, [Scope::LINE_ITEM], 'POST', $this->container($endpoint), MediaType::LINE_ITEM, $body, MediaType::LINE_ITEM)), $this->allowLocalhost);
    }

    /**
     * @throws ServiceFailed|InvalidData|InvalidRequest
     */
    public function updateLineItem(PlatformRegistration $platform, AgsEndpoint $endpoint, LineItem $lineItem): LineItem
    {
        $this->require($endpoint, Scope::LINE_ITEM);
        $id = $lineItem->id ?? throw InvalidRequest::because('A line item is changed by its address.');

        return LineItem::fromArray(ServiceCall::json($this->call->send($platform, [Scope::LINE_ITEM], 'PUT', $id, MediaType::LINE_ITEM, $lineItem->toArray(), MediaType::LINE_ITEM)), $this->allowLocalhost);
    }

    /**
     * @throws ServiceFailed|InvalidRequest
     */
    public function deleteLineItem(PlatformRegistration $platform, AgsEndpoint $endpoint, Url $lineItem): void
    {
        $this->require($endpoint, Scope::LINE_ITEM);
        $this->call->send($platform, [Scope::LINE_ITEM], 'DELETE', $lineItem, '*/*');
    }

    /**
     * Sends a score to a line item — the resource link's own when none is given.
     *
     * @throws ServiceFailed|InvalidRequest
     */
    public function publishScore(PlatformRegistration $platform, AgsEndpoint $endpoint, Score $score, ?Url $lineItem = null): void
    {
        $this->require($endpoint, Scope::SCORE);
        $target = $lineItem ?? $endpoint->lineItem ?? throw InvalidRequest::because('The launch names no line item to score, and none is given.');
        $this->call->send($platform, [Scope::SCORE], 'POST', UrlPath::append($target, 'scores', $this->allowLocalhost), 'application/json', $score->toArray(), MediaType::SCORE);
    }

    /**
     * The results of a line item, of one learner when given.
     *
     * @return list<Result>
     *
     * @throws ServiceFailed|InvalidData|InvalidRequest
     */
    public function results(PlatformRegistration $platform, AgsEndpoint $endpoint, Url $lineItem, ?string $userId = null): array
    {
        $this->require($endpoint, Scope::RESULT_READ_ONLY);
        $url = UrlPath::append($lineItem, 'results', $this->allowLocalhost);

        if (null !== $userId) {
            $url = UrlQuery::with($url, ['user_id' => $userId], $this->allowLocalhost);
        }

        $items = ServiceCall::json($this->call->send($platform, [Scope::RESULT_READ_ONLY], 'GET', $url, MediaType::RESULT_CONTAINER));

        if (!array_is_list($items)) {
            throw InvalidData::because('The results of a line item are a list.');
        }

        return array_map(fn (mixed $item): Result => Result::fromArray(\is_array($item) ? $item : [], $this->allowLocalhost), $items);
    }

    private function container(AgsEndpoint $endpoint): Url
    {
        return $endpoint->lineItems ?? throw InvalidRequest::because('The launch names no line items of the context.');
    }

    private function readScope(AgsEndpoint $endpoint): string
    {
        if ($endpoint->allows(Scope::LINE_ITEM)) {
            return Scope::LINE_ITEM;
        }

        $this->require($endpoint, Scope::LINE_ITEM_READ_ONLY);

        return Scope::LINE_ITEM_READ_ONLY;
    }

    private function require(AgsEndpoint $endpoint, string $scope): void
    {
        if (!$endpoint->allows($scope)) {
            throw InvalidRequest::because(\sprintf('The launch does not grant the scope "%s".', $scope));
        }
    }
}
