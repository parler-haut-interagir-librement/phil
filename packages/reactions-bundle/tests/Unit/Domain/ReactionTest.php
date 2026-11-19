<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reaction::class)]
#[CoversClass(InvalidReaction::class)]
final class ReactionTest extends TestCase
{
    public function testAReactionKeepsSeconds(): void
    {
        $reaction = Reaction::of('r1', ResourceReference::of('article', '1'), 'ada', 'like', new DateTimeImmutable('2026-05-01 10:00:00.5'));

        self::assertSame(['r1', 'article:1', 'ada', 'like'], [$reaction->id, $reaction->content, $reaction->member, $reaction->code()]);
        self::assertSame('2026-05-01 10:00:00.000000', $reaction->reactedAt()->format('Y-m-d H:i:s.u'));
    }

    public function testChangingKeepsTheReaction(): void
    {
        $reaction = Reaction::of('r1', ResourceReference::of('article', '1'), 'ada', 'like', new DateTimeImmutable('2026-05-01 10:00:00'));
        $reaction->changeTo('thanks', new DateTimeImmutable('2026-05-02 11:00:00.25'));

        self::assertSame(['r1', 'thanks', '2026-05-02 11:00:00.000000'], [$reaction->id, $reaction->code(), $reaction->reactedAt()->format('Y-m-d H:i:s.u')]);

        $this->expectException(InvalidReaction::class);
        $reaction->changeTo('NOT A CODE', new DateTimeImmutable());
    }

    public function testRestoredAsStored(): void
    {
        $at = new DateTimeImmutable('2026-05-01 10:00:00');
        $reaction = Reaction::restored('r1', 'article:1', 'ada', 'like', $at);

        self::assertSame(['r1', 'article:1', 'ada', 'like', $at], [$reaction->id, $reaction->content, $reaction->member, $reaction->code(), $reaction->reactedAt()]);
    }

    public function testAMemberAndACodeAreChecked(): void
    {
        $content = ResourceReference::of('article', '1');
        $now = new DateTimeImmutable();

        self::assertSame(str_repeat('m', 255), Reaction::of('r', $content, str_repeat('m', 255), 'like', $now)->member);

        foreach (['', str_repeat('m', 256)] as $member) {
            try {
                Reaction::of('r', $content, $member, 'like', $now);
                self::fail('Accepted.');
            } catch (InvalidReaction $invalidReaction) {
                self::assertSame('A member identifier is 1 to 255 bytes.', $invalidReaction->getMessage());
            }
        }

        foreach (['ada@example.com', '@', 'a@'] as $member) {
            try {
                Reaction::of('r', $content, $member, 'like', $now);
                self::fail('Accepted.');
            } catch (InvalidReaction $invalidReaction) {
                self::assertSame('A member identifier is a stable identifier the site gives, never an email address: it cannot contain "@".', $invalidReaction->getMessage());
                self::assertStringNotContainsString('example', $invalidReaction->getMessage());
            }
        }

        self::assertSame('0192f3c4-user', Reaction::member('0192f3c4-user'));
        self::assertSame('m', Reaction::member('m'));

        $this->expectException(InvalidReaction::class);
        Reaction::of('r', $content, 'ada', 'NOT', $now);
    }
}
