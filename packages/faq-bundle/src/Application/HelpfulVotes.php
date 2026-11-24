<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Phil\FaqBundle\Application\Contract\Throttle;
use Phil\FaqBundle\Application\Exception\AlreadyVoted;
use Phil\FaqBundle\Application\Exception\TooManyRequests;
use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\VoteRefused;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Contract\SpentVoteTokenRepository;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;
use Psr\Clock\ClockInterface;

/**
 * The "Was this useful?" votes: yes or no, counted per question, once per page token, held back per question. Nothing
 * of the reader is read or kept: no address, no identifier, no cookie.
 */
final readonly class HelpfulVotes
{
    public function __construct(
        private FaqRepository $repository,
        private SpentVoteTokenRepository $spent,
        private VoteTokens $tokens,
        private Throttle $throttle,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws UnknownQuestion when no question has this anchor
     * @throws VoteRefused     when the token is not valid
     * @throws TooManyRequests when the question received too many votes lately
     * @throws AlreadyVoted    when the token already voted for this question
     */
    public function vote(string $anchor, bool $helpful, string $token): VoteCount
    {
        $question = $this->repository->questionByAnchor($anchor) ?? throw UnknownQuestion::named($anchor);
        $ticket = $this->tokens->read($token);
        $this->throttle->consume($question->id);
        $this->spent->purge($this->clock->now());

        if (!$this->spent->spend($ticket->spentId($question->id), $ticket->expiresAt)) {
            throw AlreadyVoted::once();
        }

        return $this->repository->countVote($question->id, $helpful) ?? throw UnknownQuestion::named($anchor);
    }
}
