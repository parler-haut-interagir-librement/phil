<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use Phil\BlockEditorBundle\Application\Reference\Suggestion;
use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberReferenceSearch::class)]
final class MemberReferenceSearchTest extends TestCase
{
    public function testTheMembersAreSuggestedWithTheirHandles(): void
    {
        $directory = new Directory();
        $directory->members[] = new Member(ResourceReference::of('user', 'a b'), 'ana', 'Ana');
        $directory->members[] = new Member(ResourceReference::of('user', '5'), 'anna', 'Anna');

        $suggestions = iterator_to_array(new MemberReferenceSearch($directory)->search('@a', 8), false);

        self::assertSame(
            [['user:1', 'Ada Lovelace', '/members/ada', '@ada'], ['user:5', 'Anna', null, '@anna']],
            array_map(static fn (Suggestion $suggestion): array => [$suggestion->reference, $suggestion->label, $suggestion->url, $suggestion->hint], $suggestions),
        );
        self::assertSame([['search', ['a', 8]]], $directory->calls);
    }
}
