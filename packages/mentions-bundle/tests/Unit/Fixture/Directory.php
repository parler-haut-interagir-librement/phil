<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Fixture;

use Override;
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Member;
use Phil\Resource\ResourceReference;

/**
 * A directory of three members — ada (`user:1`, with a profile), bob (`user:2`, notified elsewhere) and chloé
 * (`user:3`, without a profile) — which records its calls; the references of `$hidden` may see nothing.
 */
final class Directory implements MemberDirectory
{
    /** @var list<array{string, mixed}> */
    public array $calls = [];

    /** @var list<Member> */
    public array $members;

    /**
     * @param list<string> $hidden
     */
    public function __construct(
        public array $hidden = [],
    ) {
        $this->members = [
            new Member(ResourceReference::of('user', '1'), 'ada', 'Ada Lovelace', '/members/ada'),
            new Member(ResourceReference::of('user', '2'), 'Bob', 'Bob Marley', 'https://example.test/bob', 'b-2'),
            new Member(ResourceReference::of('user', '3'), 'chloé', 'Chloé Zhao'),
        ];
    }

    #[Override]
    public function resolve(array $handles): iterable
    {
        $this->calls[] = ['resolve', $handles];
        $lower = array_map(mb_strtolower(...), $handles);

        return array_values(array_filter($this->members, static fn (Member $member): bool => \in_array(mb_strtolower($member->handle), $lower, true)));
    }

    #[Override]
    public function members(array $references): iterable
    {
        $this->calls[] = ['members', array_map(static fn (ResourceReference $reference): string => $reference->toString(), $references)];

        foreach ($this->members as $member) {
            foreach ($references as $reference) {
                if ($member->reference->equals($reference)) {
                    yield $member;
                }
            }
        }
    }

    #[Override]
    public function search(string $query, int $limit): iterable
    {
        $this->calls[] = ['search', [$query, $limit]];

        return \array_slice(array_values(array_filter($this->members, static fn (Member $member): bool => str_starts_with(mb_strtolower($member->handle), mb_strtolower($query)))), 0, $limit);
    }

    #[Override]
    public function maySee(ResourceReference $member, ResourceReference $content): bool
    {
        $this->calls[] = ['maySee', [$member->toString(), $content->toString()]];

        return !\in_array($member->toString(), $this->hidden, true);
    }
}
