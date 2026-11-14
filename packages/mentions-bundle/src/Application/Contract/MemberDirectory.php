<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application\Contract;

use Phil\MentionsBundle\Application\Member;
use Phil\Resource\ResourceReference;

/**
 * The members of the application, as mentions need them: found by handle or by reference, searched as a writer types,
 * and asked whether they may see a piece of content. The application writes it on its own users, and answers each
 * call in one query.
 */
interface MemberDirectory
{
    /**
     * The members whose handle is among these, compared without case; a handle nobody has is left out.
     *
     * @param non-empty-list<string> $handles
     *
     * @return iterable<Member>
     */
    public function resolve(array $handles): iterable;

    /**
     * The members of these references; a reference to nobody — a deleted account — is left out.
     *
     * @param non-empty-list<ResourceReference> $references
     *
     * @return iterable<Member>
     */
    public function members(array $references): iterable;

    /**
     * The members a writer may mention whose handle or name begins with the query, the best first, at most `$limit`.
     *
     * @param string $query what was typed after `@`, possibly empty
     *
     * @return iterable<Member>
     */
    public function search(string $query, int $limit): iterable;

    /**
     * Whether the member may see the content: a member who may not is neither recorded nor notified.
     */
    public function maySee(ResourceReference $member, ResourceReference $content): bool;
}
