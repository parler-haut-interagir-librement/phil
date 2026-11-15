<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Service\Ags\Score;

/**
 * The latest score of each learner on each line item.
 */
interface ScoreStore
{
    public function latest(string $lineItemId, string $userId): ?Score;

    /**
     * Keeps the score as the learner's latest on the line item, in place of the one before.
     */
    public function record(string $lineItemId, Score $score): void;

    /**
     * The latest scores of a line item, of one learner when given, in a stable order.
     *
     * @return list<Score>
     */
    public function scores(string $lineItemId, ?string $userId, int $limit, int $offset): array;
}
