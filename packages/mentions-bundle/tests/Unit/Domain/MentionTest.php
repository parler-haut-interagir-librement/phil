<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Domain;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Exception\MentionsException;
use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mention::class)]
#[CoversClass(InvalidMention::class)]
final class MentionTest extends TestCase
{
    public function testAHandleIsTypedAtAnOffset(): void
    {
        $mention = Mention::ofHandle('Ada', 0);

        self::assertSame(['Ada', null, '@Ada', 0], [$mention->handle, $mention->member, $mention->label, $mention->offset]);
        self::assertSame('handle:ada', $mention->key());
        self::assertFalse($mention->isMark());
        self::assertSame('handle:élodie', Mention::ofHandle('ÉLODIE', 7)->key());
    }

    public function testAMarkNamesItsMember(): void
    {
        $member = ResourceReference::of('user', '42');
        $mention = Mention::ofMember($member, '@Ada');

        self::assertSame([null, $member, '@Ada', null], [$mention->handle, $mention->member, $mention->label, $mention->offset]);
        self::assertSame('member:user:42', $mention->key());
        self::assertTrue($mention->isMark());
    }

    public function testAnEmptyHandleOrANegativeOffsetIsRefused(): void
    {
        foreach ([['', 0, 'A mention names a handle.'], ['ada', -1, 'The offset of a mention is not negative.']] as [$handle, $offset, $message]) {
            try {
                Mention::ofHandle($handle, $offset);
                self::fail($message);
            } catch (InvalidMention $invalidMention) {
                self::assertInstanceOf(MentionsException::class, $invalidMention);
                self::assertSame($message, $invalidMention->getMessage());
            }
        }
    }
}
