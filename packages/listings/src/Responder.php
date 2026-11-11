<?php

declare(strict_types=1);

namespace Phil\Listings;

use DateTimeImmutable;
use Phil\Listings\Contract\BlockList;
use Phil\Listings\Contract\MemberAges;
use Phil\Listings\Contract\ResponseStore;
use Phil\Listings\Exception\ForbiddenTransition;
use Phil\Listings\Exception\ResponseRefused;
use Phil\Listings\Exception\UnknownKind;
use Psr\Clock\ClockInterface;

/**
 * The rules that span a listing and its responses: who may respond, and whether an author may still accept.
 *
 * It keeps no state of its own between calls: every rule reads the ports, every response is written to the store.
 * It is safe to share across the requests of a long-running worker.
 */
final readonly class Responder
{
    public function __construct(
        private KindPolicies $policies,
        private BlockList $blocks,
        private MemberAges $ages,
        private ResponseStore $responses,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Why the member may not respond to the listing, or null when they may. The rules are asked from the cheapest
     * to the costliest; the first that refuses answers.
     *
     * @throws UnknownKind
     */
    public function refusal(Listing $listing, MemberId $respondent): ?Refusal
    {
        $now = $this->clock->now();
        $policy = $this->policies->get($listing->kind);

        return match (true) {
            !$listing->isVisibleAt($now) => Refusal::NotOpen,
            !$policy->acceptsResponses => Refusal::ResponsesClosed,
            $respondent->equals($listing->author) => Refusal::OwnListing,
            $this->blocks->isBlocked($listing->author, $respondent) => Refusal::Blocked,
            default => $this->ageRefusal($listing, $respondent, $now) ?? $this->countRefusal($listing, $policy, $respondent),
        };
    }

    /**
     * Sends the member's response, pending until the author decides.
     *
     * @throws ResponseRefused
     * @throws UnknownKind
     */
    public function respond(Listing $listing, ResponseId $id, MemberId $respondent): Response
    {
        $refusal = $this->refusal($listing, $respondent);

        if ($refusal instanceof Refusal) {
            throw ResponseRefused::for($refusal);
        }

        $response = Response::pending($id, $listing->id, $respondent, $this->clock->now());
        $this->responses->save($response);

        return $response;
    }

    /**
     * The author accepts a pending response to their listing, while the listing is visible, unless one of them has
     * blocked the other since or the respondent no longer meets the listing's minimum age.
     *
     * @throws ResponseRefused
     * @throws ForbiddenTransition
     */
    public function accept(Listing $listing, Response $response): Response
    {
        $this->expectOwn($listing, $response);
        $now = $this->clock->now();

        $refusal = match (true) {
            !$listing->isVisibleAt($now) => Refusal::NotOpen,
            $this->blocks->isBlocked($listing->author, $response->respondent) => Refusal::Blocked,
            default => $this->ageRefusal($listing, $response->respondent, $now),
        };

        if ($refusal instanceof Refusal) {
            throw ResponseRefused::for($refusal);
        }

        $accepted = $response->accept($now);
        $this->responses->save($accepted);

        return $accepted;
    }

    /**
     * The author declines a pending response to their listing.
     *
     * @throws ResponseRefused
     * @throws ForbiddenTransition
     */
    public function decline(Listing $listing, Response $response): Response
    {
        $this->expectOwn($listing, $response);

        $declined = $response->decline($this->clock->now());
        $this->responses->save($declined);

        return $declined;
    }

    /**
     * The respondent takes their response back.
     *
     * @throws ForbiddenTransition
     */
    public function withdraw(Response $response): Response
    {
        $withdrawn = $response->withdraw($this->clock->now());
        $this->responses->save($withdrawn);

        return $withdrawn;
    }

    private function ageRefusal(Listing $listing, MemberId $respondent, DateTimeImmutable $now): ?Refusal
    {
        if ($listing->admitsAge(null)) {
            return null;
        }

        $age = $this->ages->ageOf($respondent, $now);

        if (null === $age) {
            return Refusal::AgeUnknown;
        }

        return $listing->admitsAge($age) ? null : Refusal::TooYoung;
    }

    private function countRefusal(Listing $listing, KindPolicy $policy, MemberId $respondent): ?Refusal
    {
        if ($this->responses->hasOpen($listing->id, $respondent)) {
            return Refusal::AlreadyResponded;
        }

        return $this->responses->countOpen($listing->id) >= $policy->maxOpenResponses ? Refusal::Full : null;
    }

    private function expectOwn(Listing $listing, Response $response): void
    {
        if (!$response->listing->equals($listing->id)) {
            throw ResponseRefused::for(Refusal::OtherListing);
        }
    }
}
