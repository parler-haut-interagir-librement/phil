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
     * Keeps the score as the learner's latest on the line item, in place of the one before, unless the one before is
     * more recent: the comparison and the write are one atomic step, so that of two scores written at once the more
     * recent stays, whatever their order. A score of the same moment as the one kept replaces it.
     *
     * @return bool true when the score is kept, false when the one kept is more recent
     */
    public function record(string $lineItemId, Score $score): bool;

    /**
     * The latest scores of a line item, of one learner when given, in a stable order.
     *
     * @return list<Score>
     */
    public function scores(string $lineItemId, ?string $userId, int $limit, int $offset): array;
}
