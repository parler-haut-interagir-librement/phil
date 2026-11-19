<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Internal\Identifier;

/**
 * What the jury evaluates, named by the application's identifier — a talk, an application, an entry —, with its
 * authors' identifiers, which the board uses for conflicts of interest and hides from reviewers in a blind campaign.
 * Its content stays in the application.
 */
final readonly class Proposal
{
    public const int MAX_AUTHORS = 50;

    public string $campaign;

    public string $id;

    /** @var list<string> */
    public array $authors;

    /**
     * @param list<string> $authors
     *
     * @throws InvalidInput
     */
    public function __construct(string $campaign, string $id, array $authors)
    {
        $this->campaign = Identifier::check($campaign, 'campaign');
        $this->id = Identifier::check($id, 'proposal');

        if (\count($authors) > self::MAX_AUTHORS) {
            throw InvalidInput::because(\sprintf('A proposal has at most %d authors.', self::MAX_AUTHORS));
        }

        $this->authors = Identifier::unique($authors, 'author');
    }

    public function isAuthoredBy(string $person): bool
    {
        return \in_array($person, $this->authors, true);
    }
}
