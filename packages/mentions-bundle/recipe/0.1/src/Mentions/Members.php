<?php

declare(strict_types=1);

namespace App\Mentions;

use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Member;
use Phil\Resource\ResourceReference;

/**
 * The members of the application, as phil/mentions-bundle finds them. Until it reads the application's accounts it finds
 * nobody: no handle is linked, nobody is notified, the `@` search lists no one.
 */
final readonly class Members implements MemberDirectory
{
    /**
     * @return iterable<Member>
     */
    public function resolve(array $handles): iterable
    {
        return [];
    }

    /**
     * @return iterable<Member>
     */
    public function members(array $references): iterable
    {
        return [];
    }

    /**
     * @return iterable<Member>
     */
    public function search(string $query, int $limit): iterable
    {
        return [];
    }

    public function maySee(ResourceReference $member, ResourceReference $content): bool
    {
        return false;
    }
}
