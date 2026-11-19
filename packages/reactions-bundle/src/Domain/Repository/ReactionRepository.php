<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\Repository;

use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\Resource\ResourceReference;

/**
 * Where reactions are kept. The contents of the batch reads are `kind:identifier` references written out, so that one
 * query answers a whole page.
 */
interface ReactionRepository
{
    /**
     * The reactions of a member to a content, oldest first.
     *
     * @return list<Reaction>
     */
    public function of(ResourceReference $content, string $member): array;

    /**
     * @throws AlreadyReacted when the member has given this code to the content
     */
    public function add(Reaction $reaction): void;

    public function save(Reaction $reaction): void;

    public function remove(Reaction $reaction): void;

    /**
     * How many members chose each code, by content; a content without reaction is absent.
     *
     * @param list<string> $contents
     *
     * @return array<string, array<string, int>>
     */
    public function counts(array $contents): array;

    /**
     * The codes a member chose, by content, oldest first; a content the member did not react to is absent.
     *
     * @param list<string> $contents
     *
     * @return array<string, list<string>>
     */
    public function chosenBy(string $member, array $contents): array;

    /**
     * The first members to have reacted, at most `$perCode` for each code of each content.
     *
     * @param list<string> $contents
     * @param int<1, max>  $perCode
     *
     * @return array<string, array<string, list<string>>>
     */
    public function reactors(array $contents, int $perCode): array;

    /**
     * @return list<Reaction>
     */
    public function ofMember(string $member): array;

    public function eraseMember(string $member): void;

    public function eraseContent(ResourceReference $content): void;
}
