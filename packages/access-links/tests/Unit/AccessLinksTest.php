<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\Exception\AlreadyInvited;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\Exception\InvitationClosed;
use Phil\AccessLinks\Exception\LinkNotUsable;
use Phil\AccessLinks\Exception\UnknownInvitation;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\Invitation;
use Phil\AccessLinks\IssuedLink;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\ReminderSchedule;
use Phil\AccessLinks\Tests\Unit\Fixture\RecordingSender;
use Phil\AccessLinks\TokenHasher;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AccessLinks::class)]
#[CoversClass(AlreadyInvited::class)]
#[CoversClass(InvitationClosed::class)]
#[CoversClass(LinkNotUsable::class)]
#[CoversClass(UnknownInvitation::class)]
final class AccessLinksTest extends TestCase
{
    private const string KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private InMemoryInvitationStore $store;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryInvitationStore();
        $this->clock = new MockClock('2026-11-13 10:00:00', 'UTC');
    }

    public function testAnInvitationIssuesAPersonalLinkStoredHashed(): void
    {
        $link = $this->links()->invite('poll-1', 'member-7', $this->clock->now()->modify('+7 days'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $link->invitation->id);
        self::assertSame('poll-1', $link->invitation->campaign);
        self::assertSame('member-7', $link->invitation->recipient);
        self::assertSame('2026-11-13 10:00:00', $link->invitation->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-20 10:00:00', $link->invitation->closesAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-16 10:00:00', $link->invitation->nextReminderAt?->format('Y-m-d H:i:s'));
        self::assertSame(43, \strlen($link->token));
        self::assertSame('https://events.example/rsvp?token='.$link->token, $link->url->toString());
        self::assertStringNotContainsString('member-7', $link->url->toString());
        self::assertSame($link->invitation->id, $this->store->findByToken(new TokenHasher(self::KEY)->candidates($link->token))?->id);
    }

    public function testTheMomentsOfTheLinkAreKept(): void
    {
        $now = $this->clock->now();
        $link = $this->links()->invite('poll-1', 'member-7', $now->modify('+2 days'), $now->modify('+9 days'), $now->modify('+1 day'));

        self::assertSame('2026-11-14 10:00:00', $link->invitation->validFrom->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-22 10:00:00', $link->invitation->expiresAt->format('Y-m-d H:i:s'));
    }

    public function testARecipientIsInvitedOnce(): void
    {
        $links = $this->links();
        $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));

        $this->expectException(AlreadyInvited::class);
        $this->expectExceptionMessageIsOrContains('The recipient "member-7" is already invited to "poll-1": issue the link again instead.');

        $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));
    }

    public function testAnInvitationClosesAfterItIsSent(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('An invitation closes after it is sent.');

        $this->links()->invite('poll-1', 'member-7', $this->clock->now());
    }

    public function testARecipientIsNeverAnEmailAddress(): void
    {
        $this->expectException(InvalidConfiguration::class);

        $this->links()->invite('poll-1', 'alice@example.com', $this->clock->now()->modify('+1 day'));
    }

    public function testTheLinkOpensWithoutChangingAnything(): void
    {
        $links = $this->links();
        $link = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'), $this->clock->now()->modify('+2 days'));

        $access = $links->open($link->token);
        self::assertNotNull($access);
        self::assertSame($link->invitation->id, $access->invitation->id);
        self::assertTrue($access->editable);
        self::assertNull($links->invitation($link->invitation->id)->respondedAt);

        $this->clock->sleep(86_400);
        $closed = $links->open($link->token);
        self::assertNotNull($closed);
        self::assertFalse($closed->editable);

        $this->clock->sleep(86_400);
        self::assertNull($links->open($link->token));
    }

    public function testAnUnknownOrMalformedLinkOpensNothing(): void
    {
        $links = $this->links();
        $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));

        self::assertNull($links->open(str_repeat('A', 43)));
        self::assertNull($links->open('member-7'));
        self::assertNull($links->open(''));
    }

    public function testALinkNotYetValidOpensNothing(): void
    {
        $links = $this->links();
        $link = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+2 days'), validFrom: $this->clock->now()->modify('+1 day'));

        self::assertNull($links->open($link->token));

        try {
            $links->respond($link->token, 'yes');
            self::fail('An answer was recorded before the link opened.');
        } catch (LinkNotUsable $linkNotUsable) {
            self::assertSame('This link is not valid, or no longer is.', $linkNotUsable->getMessage());
        }

        $this->clock->sleep(86_400);
        self::assertNotNull($links->open($link->token));
    }

    public function testTheAnswerChangesUntilTheClosure(): void
    {
        $links = $this->links();
        $link = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'), $this->clock->now()->modify('+2 days'));

        $first = $links->respond($link->token, 'yes');
        self::assertSame('yes', $first->answer);
        self::assertSame('2026-11-13 10:00:00', $first->respondedAt?->format('Y-m-d H:i:s'));
        self::assertNull($first->nextReminderAt);

        $this->clock->sleep(3_600);
        $second = $links->respond($link->token, 'no');
        self::assertSame('no', $second->answer);
        self::assertSame('2026-11-13 10:00:00', $second->respondedAt?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-13 11:00:00', $second->answeredAt?->format('Y-m-d H:i:s'));

        $this->clock->sleep(82_800);

        try {
            $links->respond($link->token, 'maybe');
            self::fail('An answer changed after the closure.');
        } catch (InvitationClosed $invitationClosed) {
            self::assertSame('The invitation is closed: its answer can no longer change.', $invitationClosed->getMessage());
        }

        self::assertSame('no', $links->invitation($link->invitation->id)->answer);
    }

    public function testAnAnswerIsCheckedBeforeTheLink(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('An answer is 1 to 4096 bytes of UTF-8.');

        $this->links()->respond('not a token', '');
    }

    public function testAnUnknownLinkAnswersNothing(): void
    {
        $this->expectException(LinkNotUsable::class);

        $this->links()->respond(str_repeat('A', 43), 'yes');
    }

    public function testARevokedInvitationOpensNothing(): void
    {
        $links = $this->links();
        $link = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));

        self::assertTrue($links->revoke($link->invitation->id));
        self::assertFalse($links->revoke($link->invitation->id));
        self::assertNull($links->open($link->token));

        $this->expectException(LinkNotUsable::class);

        $links->respond($link->token, 'yes');
    }

    public function testALinkIssuedAgainOpensBesideTheFirst(): void
    {
        $links = $this->links();
        $first = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));
        $again = $links->reissue($first->invitation->id);

        self::assertNotSame($first->token, $again->token);
        self::assertSame($first->invitation->id, $links->open($again->token)?->invitation->id);
        self::assertSame($first->invitation->id, $links->open($first->token)?->invitation->id);
    }

    public function testSixteenLinksAtTheMost(): void
    {
        $links = $this->links();
        $first = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));

        for ($i = 1; $i < 16; ++$i) {
            $links->reissue($first->invitation->id);
        }

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('An invitation has 16 links at the most.');

        $links->reissue($first->invitation->id);
    }

    public function testRemindersReachOnlyThoseWhoHaveNotAnswered(): void
    {
        $links = $this->links();
        $sender = new RecordingSender();
        $silent = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+10 days'));
        $answered = $links->invite('poll-1', 'member-8', $this->clock->now()->modify('+10 days'));
        $links->respond($answered->token, 'yes');

        self::assertSame([], $links->dueReminders());
        $this->clock->sleep(3 * 86_400);
        self::assertSame([$silent->invitation->id], $links->dueReminders());
        self::assertSame([$silent->invitation->id], $links->dueReminders(1));

        self::assertTrue($links->remind($silent->invitation->id, $sender));
        self::assertFalse($links->remind($silent->invitation->id, $sender));
        self::assertFalse($links->remind($answered->invitation->id, $sender));

        self::assertCount(1, $sender->sent);
        $reminder = $sender->sent[0];
        self::assertSame($silent->invitation->id, $reminder->invitation->id);
        self::assertSame(1, $reminder->invitation->remindersSent);
        self::assertSame('2026-11-20 10:00:00', $reminder->invitation->nextReminderAt?->format('Y-m-d H:i:s'));
        self::assertNotSame($silent->token, $reminder->token);
        self::assertSame($silent->invitation->id, $links->open($reminder->token)?->invitation->id);
        self::assertSame('https://events.example/rsvp?token='.$reminder->token, $reminder->url->toString());
        self::assertSame([], $links->dueReminders());

        $this->clock->sleep(4 * 86_400);
        self::assertTrue($links->remind($silent->invitation->id, $sender));
        self::assertNull($links->invitation($silent->invitation->id)->nextReminderAt);
        self::assertSame(2, $links->invitation($silent->invitation->id)->remindersSent);
    }

    public function testAReminderWithoutLinksLeftSendsNothing(): void
    {
        $links = $this->links();
        $sender = new RecordingSender();
        $link = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+10 days'));

        for ($i = 1; $i < 16; ++$i) {
            $links->reissue($link->invitation->id);
        }

        $this->clock->sleep(3 * 86_400);

        self::assertFalse($links->remind($link->invitation->id, $sender));
        self::assertSame([], $sender->sent);
    }

    public function testAnUnknownInvitationIsNamed(): void
    {
        $links = $this->links();

        foreach ([
            static fn (): Invitation => $links->invitation('nope'),
            static fn (): IssuedLink => $links->reissue('nope'),
            static fn (): bool => $links->revoke('nope'),
            static fn (): bool => $links->remind('nope', new RecordingSender()),
        ] as $call) {
            try {
                $call();
                self::fail('An unknown invitation was answered.');
            } catch (UnknownInvitation $exception) {
                self::assertSame('No invitation has the identifier "nope".', $exception->getMessage());
            }
        }
    }

    public function testACampaignIsCounted(): void
    {
        $links = $this->links();
        $first = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));
        $links->invite('poll-1', 'member-8', $this->clock->now()->modify('+1 day'));
        $links->respond($first->token, 'yes');

        $counts = $links->counts('poll-1');
        self::assertSame([2, 1, 0, 1], [$counts->invited, $counts->responded, $counts->revoked, $counts->pending()]);
    }

    public function testTheDefaultGeneratorIsSecureAndTheDefaultScheduleSendsNothing(): void
    {
        $links = new AccessLinks($this->store, new TokenHasher(self::KEY), new LinkFormat(Url::fromString('https://events.example/rsvp')), $this->clock);
        $link = $links->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));

        self::assertNull($link->invitation->nextReminderAt);
        self::assertNotSame($link->token, $links->reissue($link->invitation->id)->token);
    }

    public function testTheRandomizerGivenDecidesTheTokens(): void
    {
        $first = $this->links()->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));
        $this->store = new InMemoryInvitationStore();
        $second = $this->links()->invite('poll-1', 'member-7', $this->clock->now()->modify('+1 day'));

        self::assertSame($first->token, $second->token);
        self::assertSame($first->invitation->id, $second->invitation->id);
    }

    public function testOneHundredDueRemindersAreListedByDefault(): void
    {
        $links = $this->links();

        for ($i = 0; $i < 101; ++$i) {
            $links->invite('poll-1', 'member-'.$i, $this->clock->now()->modify('+10 days'));
        }

        $this->clock->sleep(86_400 * 4);

        self::assertCount(100, $links->dueReminders());
        self::assertCount(101, $links->dueReminders(500));
    }

    private function links(): AccessLinks
    {
        return new AccessLinks(
            $this->store,
            new TokenHasher(self::KEY),
            new LinkFormat(Url::fromString('https://events.example/rsvp')),
            $this->clock,
            new ReminderSchedule(['P3D', 'P7D']),
            new Randomizer(new Xoshiro256StarStar(13)),
        );
    }
}
