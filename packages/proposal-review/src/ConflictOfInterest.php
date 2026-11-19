<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Internal\Identifier;

/**
 * A reviewer who must neither see nor score a proposal: declared for one proposal, or for one author and then for every
 * proposal of theirs. A reviewer is always in conflict with their own proposals, declared or not.
 */
final readonly class ConflictOfInterest
{
    private function __construct(
        public string $campaign,
        public string $reviewer,
        public ?string $proposal,
        public ?string $author,
    ) {
    }

    public static function withProposal(string $campaign, string $reviewer, string $proposal): self
    {
        return new self(Identifier::check($campaign, 'campaign'), Identifier::check($reviewer, 'reviewer'), Identifier::check($proposal, 'proposal'), null);
    }

    public static function withAuthor(string $campaign, string $reviewer, string $author): self
    {
        return new self(Identifier::check($campaign, 'campaign'), Identifier::check($reviewer, 'reviewer'), null, Identifier::check($author, 'author'));
    }

    public function concerns(Proposal $proposal): bool
    {
        return $proposal->campaign === $this->campaign
            && ($proposal->id === $this->proposal || (null !== $this->author && $proposal->isAuthoredBy($this->author)));
    }
}
