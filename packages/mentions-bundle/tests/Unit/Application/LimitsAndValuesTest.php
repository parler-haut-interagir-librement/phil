<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Application;

use Phil\MentionsBundle\Application\AlwaysNotify;
use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Application\MentionChanges;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;
use Phil\MentionsBundle\Domain\ValueObject\MemberIdentifier;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MentionLimits::class)]
#[CoversClass(MentionsRefused::class)]
#[CoversClass(Member::class)]
#[CoversClass(MentionChanges::class)]
#[CoversClass(AlwaysNotify::class)]
#[CoversClass(MemberIdentifier::class)]
final class LimitsAndValuesTest extends TestCase
{
    public function testTheLimits(): void
    {
        $limits = new MentionLimits();
        self::assertSame([20, 100_000], [$limits->perText, $limits->textLength]);

        $limits = new MentionLimits(2, 5);
        $limits->checkCount(2);
        $limits->checkLength('ééééé');

        try {
            $limits->checkCount(3);
            self::fail('three mentions');
        } catch (MentionsRefused $mentionsRefused) {
            self::assertSame('A text mentions 2 members at most; this one mentions 3.', $mentionsRefused->getMessage());
        }

        try {
            $limits->checkLength('éééééé');
            self::fail('six characters');
        } catch (MentionsRefused $mentionsRefused) {
            self::assertSame('A text read for mentions holds 5 characters at most; this one holds 6.', $mentionsRefused->getMessage());
        }

        self::assertSame([1, 1], [new MentionLimits(1, 1)->perText, new MentionLimits(1, 1)->textLength]);
        self::assertSame([1000, 10_000_000], [new MentionLimits(1000, 10_000_000)->perText, new MentionLimits(1000, 10_000_000)->textLength]);
    }

    public function testLimitsOutOfTheirRange(): void
    {
        foreach ([[0, 1, 'members'], [1001, 1, 'members'], [1, 0, 'characters'], [1, 10_000_001, 'characters']] as [$perText, $length, $word]) {
            try {
                new MentionLimits($perText, $length);
                self::fail($word);
            } catch (InvalidMention $invalidMention) {
                self::assertStringContainsString($word, $invalidMention->getMessage());
            }
        }
    }

    public function testAMember(): void
    {
        $member = new Member(ResourceReference::of('user', '1'), 'ada', 'Ada');
        self::assertSame(['user:1', 'ada', 'Ada', null, 'user:1'], [$member->reference->toString(), $member->handle, $member->name, $member->url, $member->recipient]);
        self::assertSame(['/m/ada', 'u-1'], [new Member(ResourceReference::of('user', '1'), 'ada', 'Ada', '/m/ada', 'u-1')->url, new Member(ResourceReference::of('user', '1'), 'ada', 'Ada', '/m/ada', 'u-1')->recipient]);

        foreach ([['', 'Ada'], ['ada', '']] as [$handle, $name]) {
            try {
                new Member(ResourceReference::of('user', '1'), $handle, $name);
                self::fail($handle.$name);
            } catch (InvalidMention $invalidMention) {
                self::assertSame('The member "user:1" has a handle and a name.', $invalidMention->getMessage());
            }
        }
    }

    public function testAMemberIsNamedByAStableIdentifierNeverAnEmailAddress(): void
    {
        $message = 'A member is named by a stable identifier the application gives, never an email address: it cannot contain "@".';

        foreach ([[ResourceReference::of('user', 'ada@example.com'), null], [ResourceReference::of('user', 'ada@example.com'), 'u-1'], [ResourceReference::of('user', '1'), 'ada@example.com']] as [$reference, $recipient]) {
            try {
                new Member($reference, 'ada', 'Ada', null, $recipient);
                self::fail($message);
            } catch (InvalidMention $invalidMention) {
                self::assertSame($message, $invalidMention->getMessage());
            }
        }

        self::assertSame('user:ada.example', MemberIdentifier::checked('user:ada.example'));
    }

    public function testChangesAndTheDefaultPreference(): void
    {
        $member = new Member(ResourceReference::of('user', '1'), 'ada', 'Ada');

        self::assertTrue(new MentionChanges()->isEmpty());
        self::assertFalse(new MentionChanges([$member])->isEmpty());
        self::assertFalse(new MentionChanges([], [$member->reference])->isEmpty());
        self::assertTrue(new AlwaysNotify()->notifies('anyone'));
    }
}
