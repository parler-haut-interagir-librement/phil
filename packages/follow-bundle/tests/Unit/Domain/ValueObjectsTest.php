<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Domain;

use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventPreferences::class)]
#[CoversClass(MemberPair::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidFollow::class)]
final class ValueObjectsTest extends TestCase
{
    public function testThePreferencesAreSortedAndAnUnnamedEventIsReadAtTheMentionsLevel(): void
    {
        $preferences = new EventPreferences(['reply.created' => FollowLevel::Watching, 'edit.made' => FollowLevel::Ignoring]);

        self::assertSame(['edit.made' => 'ignoring', 'reply.created' => 'watching'], $preferences->toArray());
        self::assertSame(FollowLevel::Watching, $preferences->levelFor('reply.created'));
        self::assertSame(FollowLevel::Mentions, $preferences->levelFor('poll.opened'));
    }

    public function testThePreferencesAreReadFromAFormOrARow(): void
    {
        self::assertEquals(new EventPreferences(['reply.created' => FollowLevel::Mentions]), EventPreferences::fromArray(['reply.created' => 'mentions']));

        foreach ([
            'The preferences of a follow are event keys with level names.' => [[0 => 'watching'], ['reply.created' => 1]],
            'The level of one event is "watching", "mentions" or "ignoring".' => [['reply.created' => 'loud'], ['reply.created' => 'custom']],
        ] as $message => $cases) {
            foreach ($cases as $case) {
                try {
                    EventPreferences::fromArray($case);
                    self::fail('Refused: '.json_encode($case));
                } catch (InvalidFollow $invalidFollow) {
                    self::assertSame($message, $invalidFollow->getMessage());
                }
            }
        }
    }

    public function testThePreferencesAreBounded(): void
    {
        $levels = [];

        for ($i = 0; $i < EventPreferences::MAX; ++$i) {
            $levels['event.'.$i] = FollowLevel::Watching;
        }

        self::assertCount(64, new EventPreferences($levels)->levels);
        $this->expectExceptionObject(InvalidFollow::because('A follow names 64 events at most.'));
        new EventPreferences([...$levels, 'event.extra' => FollowLevel::Watching]);
    }

    public function testAnEventIsAKey(): void
    {
        $this->expectException(InvalidFollow::class);
        new EventPreferences(['Not A Key' => FollowLevel::Watching]);
    }

    public function testTheCustomLevelIsNoLevelOfOneEvent(): void
    {
        $this->expectExceptionObject(InvalidFollow::because('The level of one event is "watching", "mentions" or "ignoring".'));
        new EventPreferences(['reply.created' => FollowLevel::Custom]);
    }

    public function testAPairIsInByteOrder(): void
    {
        $pair = MemberPair::of('b', 'a');

        self::assertSame(['a', 'b'], [$pair->first, $pair->second]);
        self::assertEquals($pair, MemberPair::of('a', 'b'));
        self::assertSame('b', $pair->other('a'));
        self::assertSame('a', $pair->other('b'));
        self::assertSame(['B', 'a'], [MemberPair::of('a', 'B')->first, MemberPair::of('a', 'B')->second]);
    }

    public function testAPairJoinsTwoMembers(): void
    {
        $this->expectExceptionObject(InvalidFollow::because('A mutual relationship joins two different members.'));
        MemberPair::of('a', 'a');
    }

    public function testAPairIsMadeOfIdentifiers(): void
    {
        foreach ([['xx yy', 'qq'], ['qq', 'zz@ww']] as [$one, $other]) {
            try {
                MemberPair::of($one, $other);
                self::fail('Refused.');
            } catch (InvalidFollow $invalidFollow) {
                self::assertStringNotContainsString('xx', $invalidFollow->getMessage());
                self::assertStringNotContainsString('zz', $invalidFollow->getMessage());
            }
        }
    }

    public function testTheIdentifiersAreThoseOfTheNotifications(): void
    {
        self::assertSame('member:42', Identifier::id('member:42', 'member'));
        self::assertSame('reply.created', Identifier::key('reply.created', 'event'));
        self::assertSame('thread:4:2', Identifier::parseSubject('thread:4:2')->type.':'.Identifier::parseSubject('thread:4:2')->id);

        foreach ([
            static fn (): string => Identifier::id('a b', 'member'),
            static fn (): string => Identifier::key('A', 'event'),
            static fn (): object => Identifier::subject('Thread', '1'),
            static fn (): object => Identifier::parseSubject('thread'),
        ] as $refused) {
            try {
                $refused();
                self::fail('Refused.');
            } catch (InvalidFollow $invalidFollow) {
                self::assertStringContainsString(' is ', $invalidFollow->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidFollow::because('A subject is written "type:identifier".'));
        Identifier::parseSubject('thread');
    }
}
