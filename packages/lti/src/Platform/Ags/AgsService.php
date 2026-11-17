<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Ags;

use Phil\Lti\Contract\LineItemStore;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Contract\ScoreStore;
use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Message\Context;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Security\Random;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Ags\Result;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;

/**
 * The Assignment and Grade Services of the platform, for a tool whose access token grants the scope of each operation,
 * in a context where the tool is placed, on the line items that are its own. A score older than the learner's latest
 * on the line item is not kept, as the specification asks; results scale the latest score to the line item's maximum.
 *
 * A method that finds no context, or no line item, of the tool answers null or false: the application answers 404.
 * The list of line items is paged as Names and Role Provisioning pages its members, with the offset of the next page.
 */
final readonly class AgsService
{
    public const int DEFAULT_LIMIT = 100;

    public const int MAX_LIMIT = 1000;

    public function __construct(
        private Roster $roster,
        private LineItemStore $lineItems,
        private ScoreStore $scores,
    ) {
    }

    /**
     * A page of the line items of a context: `limit` items at most, 100 by default and 1,000 at most — the limit the
     * page was cut at, whatever was asked —; `nextOffset` is where the next page starts, null on the last.
     *
     * @return array{lineItems: list<StoredLineItem>, limit: int<1, 1000>, nextOffset: ?int}|null null when the context
     *                                                                                            is not one of the tool
     *
     * @throws AccessDenied
     */
    public function lineItems(AccessToken $token, string $contextId, ?string $resourceLinkId = null, ?string $resourceId = null, ?string $tag = null, ?int $limit = null, int $offset = 0): ?array
    {
        $this->require($token, Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY);

        if (!$this->placed($token, $contextId)) {
            return null;
        }

        $limit = $this->limit($limit);
        $offset = max(0, $offset);
        $lineItems = $this->lineItems->list($token->clientId, $contextId, $resourceLinkId, $resourceId, $tag, $limit + 1, $offset);
        $more = \count($lineItems) > $limit;

        return ['lineItems' => \array_slice($lineItems, 0, $limit), 'limit' => $limit, 'nextOffset' => $more ? $offset + $limit : null];
    }

    /**
     * @throws AccessDenied
     */
    public function lineItem(AccessToken $token, string $contextId, string $id): ?StoredLineItem
    {
        $this->require($token, Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY);

        return $this->placed($token, $contextId) ? $this->lineItems->find($token->clientId, $contextId, $id) : null;
    }

    /**
     * @throws AccessDenied
     */
    public function create(AccessToken $token, string $contextId, LineItem $lineItem): ?StoredLineItem
    {
        $this->require($token, Scope::LINE_ITEM);

        if (!$this->placed($token, $contextId)) {
            return null;
        }

        $stored = new StoredLineItem(hash('xxh128', Random::token()), $token->clientId, $contextId, $this->withoutId($lineItem));
        $this->lineItems->save($stored);

        return $stored;
    }

    /**
     * @throws AccessDenied
     */
    public function update(AccessToken $token, string $contextId, string $id, LineItem $lineItem): ?StoredLineItem
    {
        $this->require($token, Scope::LINE_ITEM);
        $current = $this->placed($token, $contextId) ? $this->lineItems->find($token->clientId, $contextId, $id) : null;

        if (!$current instanceof StoredLineItem) {
            return null;
        }

        $stored = new StoredLineItem($id, $token->clientId, $contextId, $this->withoutId($lineItem));
        $this->lineItems->save($stored);

        return $stored;
    }

    /**
     * @throws AccessDenied
     */
    public function delete(AccessToken $token, string $contextId, string $id): bool
    {
        $this->require($token, Scope::LINE_ITEM);
        $current = $this->placed($token, $contextId) ? $this->lineItems->find($token->clientId, $contextId, $id) : null;

        if (!$current instanceof StoredLineItem) {
            return false;
        }

        $this->lineItems->delete($current);

        return true;
    }

    /**
     * Keeps a score; null when the line item is not the tool's, false when the score is older than the latest kept.
     * The store compares and writes in one atomic step: of two scores sent at once, the more recent stays.
     *
     * @throws AccessDenied
     */
    public function score(AccessToken $token, string $contextId, string $lineItemId, Score $score): ?bool
    {
        $this->require($token, Scope::SCORE);

        $stored = $this->placed($token, $contextId) ? $this->lineItems->find($token->clientId, $contextId, $lineItemId) : null;

        if (!$stored instanceof StoredLineItem) {
            return null;
        }

        return $this->scores->record($lineItemId, $score);
    }

    /**
     * @return list<Result>|null
     *
     * @throws AccessDenied
     */
    public function results(AccessToken $token, string $contextId, string $lineItemId, ?string $userId = null, ?int $limit = null, int $offset = 0): ?array
    {
        $this->require($token, Scope::RESULT_READ_ONLY);
        $stored = $this->placed($token, $contextId) ? $this->lineItems->find($token->clientId, $contextId, $lineItemId) : null;

        if (!$stored instanceof StoredLineItem) {
            return null;
        }

        $maximum = $stored->lineItem->scoreMaximum;

        return array_map(static fn (Score $score): Result => new Result(
            $score->userId,
            null === $score->scoreGiven || null === $score->scoreMaximum ? null : $score->scoreGiven * $maximum / $score->scoreMaximum,
            $maximum,
            $score->comment,
        ), $this->scores->scores($lineItemId, $userId, $this->limit($limit), max(0, $offset)));
    }

    private function placed(AccessToken $token, string $contextId): bool
    {
        return $this->roster->context($token->clientId, $contextId) instanceof Context;
    }

    private function require(AccessToken $token, string ...$scopes): void
    {
        foreach ($scopes as $scope) {
            if ($token->grants($scope)) {
                return;
            }
        }

        throw AccessDenied::insufficientScope($scopes[0]);
    }

    /**
     * @return int<1, 1000>
     */
    private function limit(?int $limit): int
    {
        return max(1, min(self::MAX_LIMIT, $limit ?? self::DEFAULT_LIMIT));
    }

    private function withoutId(LineItem $lineItem): LineItem
    {
        return new LineItem($lineItem->scoreMaximum, $lineItem->label, null, $lineItem->resourceId, $lineItem->resourceLinkId, $lineItem->tag, $lineItem->startDateTime, $lineItem->endDateTime, $lineItem->gradesReleased);
    }
}
