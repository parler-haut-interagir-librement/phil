<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordedMention::class)]
final class RecordedMentionTest extends TestCase
{
    public function testAMentionKeepsItsReferencesAndItsSecond(): void
    {
        $mention = RecordedMention::record('m-1', ResourceReference::of('comment', '7'), ResourceReference::of('user', '1'), ResourceReference::of('user', '2'), new DateTimeImmutable('2026-11-08 10:00:00.75 UTC'));

        self::assertSame('m-1', $mention->id);
        self::assertSame(['comment:7', 'user:1', 'user:2'], [$mention->subject()->toString(), $mention->member()->toString(), $mention->author()->toString()]);
        self::assertSame('2026-11-08 10:00:00.000000 +00:00', $mention->createdAt()->format('Y-m-d H:i:s.u P'));
        self::assertSame(36, \strlen(RecordedMention::record(str_repeat('a', 36), ResourceReference::of('c', '1'), ResourceReference::of('u', '1'), ResourceReference::of('u', '2'), new DateTimeImmutable())->id));
    }

    public function testAnIdentifierIsOneTo36Characters(): void
    {
        foreach (['', str_repeat('a', 37)] as $id) {
            try {
                RecordedMention::record($id, ResourceReference::of('c', '1'), ResourceReference::of('u', '1'), ResourceReference::of('u', '2'), new DateTimeImmutable());
                self::fail($id);
            } catch (InvalidMention $invalidMention) {
                self::assertSame('A mention is identified by 1 to 36 characters.', $invalidMention->getMessage());
            }
        }
    }

    public function testMembersAndAuthorsAreNeverEmailAddresses(): void
    {
        foreach ([[ResourceReference::of('u', 'a@b'), ResourceReference::of('u', '2')], [ResourceReference::of('u', '1'), ResourceReference::of('u', 'a@b')]] as [$member, $author]) {
            try {
                RecordedMention::record('m1', ResourceReference::of('c', '1'), $member, $author, new DateTimeImmutable());
                self::fail('an email address');
            } catch (InvalidMention $invalidMention) {
                self::assertStringEndsWith('it cannot contain "@".', $invalidMention->getMessage());
            }
        }
    }
}
