<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use Phil\Listings\Criteria;
use Phil\Listings\Exception\ForbiddenTransition;
use Phil\Listings\Exception\ResponseRefused;
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\Listing;
use Phil\Listings\ListingId;
use Phil\Listings\ListingKind;
use Phil\Listings\Location;
use Phil\Listings\MemberId;
use Phil\Listings\Moderation;
use Phil\Listings\ModerationReason;
use Phil\Listings\Refusal;
use Phil\Listings\Responder;
use Phil\Listings\Response;
use Phil\Listings\ResponseId;
use Phil\Listings\ResponseStatus;
use Phil\Listings\Tests\Unit\Fixture\Ages;
use Phil\Listings\Tests\Unit\Fixture\Blocks;
use Phil\Listings\Tests\Unit\Fixture\FrozenClock;
use Phil\Listings\Tests\Unit\Fixture\InMemoryResponseStore;
use Phil\Listings\Title;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Responder::class)]
#[CoversClass(ResponseRefused::class)]
final class ResponderTest extends TestCase
{
    private FrozenClock $clock;

    private Blocks $blocks;

    private Ages $ages;

    private InMemoryResponseStore $store;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->blocks = new Blocks();
        $this->ages = new Ages();
        $this->store = new InMemoryResponseStore();
    }

    public function testAMemberRespondsToAVisibleListing(): void
    {
        $listing = $this->listing(KindPolicy::of(ListingKind::of('partner')));
        $responder = $this->responder(KindPolicy::of(ListingKind::of('partner')));

        self::assertNull($responder->refusal($listing, MemberId::of('m1')));

        $response = $responder->respond($listing, ResponseId::of('r1'), MemberId::of('m1'));

        self::assertSame(ResponseStatus::Pending, $response->status);
        self::assertSame('l1', $response->listing->value);
        self::assertSame('m1', $response->respondent->value);
        self::assertSame($this->clock->now(), $response->sentAt);
        self::assertSame($response, $this->store->responses['r1']);
        self::assertSame(0, $this->ages->asked, 'No age is asked of a listing without a minimum.');
    }

    public function testAnExpiredListingTakesNoResponse(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $listing = $this->listing($policy);
        $this->clock->advance(30 * 86400);

        self::assertSame(Refusal::NotOpen, $this->responder($policy)->refusal($listing, MemberId::of('m1')));
    }

    public function testAKindWithoutResponsesTakesNone(): void
    {
        $policy = KindPolicy::of(ListingKind::of('notice'), acceptsResponses: false);

        self::assertSame(Refusal::ResponsesClosed, $this->responder($policy)->refusal($this->listing($policy), MemberId::of('m1')));
    }

    public function testTheAuthorCannotRespondToTheirOwnListing(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));

        self::assertSame(Refusal::OwnListing, $this->responder($policy)->refusal($this->listing($policy), MemberId::of('author')));
    }

    public function testABlockInEitherDirectionRefuses(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $this->blocks->pairs = [['m1', 'author'], ['author', 'm2']];

        self::assertSame(Refusal::Blocked, $this->responder($policy)->refusal($this->listing($policy), MemberId::of('m1')));
        self::assertSame(Refusal::Blocked, $this->responder($policy)->refusal($this->listing($policy), MemberId::of('m2')));
    }

    public function testTheMinimumAgeIsChecked(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), minimumAge: 18);
        $listing = $this->listing($policy);
        $responder = $this->responder($policy);
        $this->ages->ages = ['young' => 17, 'adult' => 18];

        self::assertSame(Refusal::AgeUnknown, $responder->refusal($listing, MemberId::of('unknown')));
        self::assertSame(Refusal::TooYoung, $responder->refusal($listing, MemberId::of('young')));
        self::assertNull($responder->refusal($listing, MemberId::of('adult')));
    }

    public function testTheAgeIsAskedBeforeTheResponsesAreCounted(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), minimumAge: 18, maxOpenResponses: 1);
        $listing = $this->listing($policy);
        $this->ages->ages = ['adult' => 30];
        $this->responder($policy)->respond($listing, ResponseId::of('r1'), MemberId::of('adult'));

        self::assertSame(Refusal::AgeUnknown, $this->responder($policy)->refusal($listing, MemberId::of('unknown')));
        self::assertSame(Refusal::AlreadyResponded, $this->responder($policy)->refusal($listing, MemberId::of('adult')));
    }

    public function testOneOpenResponsePerMemberAndABoundPerListing(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), maxOpenResponses: 2);
        $listing = $this->listing($policy);
        $responder = $this->responder($policy);

        $first = $responder->respond($listing, ResponseId::of('r1'), MemberId::of('m1'));
        self::assertSame(Refusal::AlreadyResponded, $responder->refusal($listing, MemberId::of('m1')));

        $responder->respond($listing, ResponseId::of('r2'), MemberId::of('m2'));
        self::assertSame(Refusal::Full, $responder->refusal($listing, MemberId::of('m3')));

        $responder->withdraw($first);
        self::assertSame(ResponseStatus::Withdrawn, $this->store->responses['r1']->status);
        self::assertNull($responder->refusal($listing, MemberId::of('m3')));
        self::assertNull($responder->refusal($listing, MemberId::of('m1')));
    }

    public function testARefusedResponseIsNotSaved(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));

        try {
            $this->responder($policy)->respond($this->listing($policy), ResponseId::of('r1'), MemberId::of('author'));
            self::fail('The author responded to their own listing.');
        } catch (ResponseRefused $responseRefused) {
            self::assertSame(Refusal::OwnListing, $responseRefused->refusal);
            self::assertSame('The response is refused: own_listing.', $responseRefused->getMessage());
        }

        self::assertSame([], $this->store->responses);
    }

    public function testTheAuthorAcceptsOrDeclines(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $listing = $this->listing($policy);
        $responder = $this->responder($policy);
        $first = $responder->respond($listing, ResponseId::of('r1'), MemberId::of('m1'));
        $second = $responder->respond($listing, ResponseId::of('r2'), MemberId::of('m2'));
        $this->clock->advance(60);

        $accepted = $responder->accept($listing, $first);
        self::assertTrue($accepted->permitsContact());
        self::assertSame($this->clock->now(), $accepted->decidedAt);
        self::assertSame($accepted, $this->store->responses['r1']);

        $declined = $responder->decline($listing, $second);
        self::assertSame(ResponseStatus::Declined, $declined->status);
        self::assertSame($this->clock->now(), $declined->decidedAt);
        self::assertSame($declined, $this->store->responses['r2']);

        $withdrawn = $responder->withdraw($accepted);
        self::assertSame($this->clock->now(), $withdrawn->decidedAt);
    }

    public function testABlockSinceTheResponseForbidsAcceptance(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $listing = $this->listing($policy);
        $response = $this->responder($policy)->respond($listing, ResponseId::of('r1'), MemberId::of('m1'));
        $this->blocks->pairs = [['author', 'm1']];

        try {
            $this->responder($policy)->accept($listing, $response);
            self::fail('A blocked response was accepted.');
        } catch (ResponseRefused $responseRefused) {
            self::assertSame(Refusal::Blocked, $responseRefused->refusal);
        }

        self::assertSame(ResponseStatus::Pending, $this->store->responses['r1']->status);
    }

    public function testAListingThatIsNoLongerVisibleAcceptsNoResponse(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), reportsToHide: 1);
        $listing = $this->listing($policy);
        $reason = ModerationReason::of('spam');
        $hidden = $listing->report($policy, MemberId::of('m9'), $this->clock->now());
        $unavailable = [
            'hidden' => $hidden,
            'removed' => $hidden->remove($policy, $reason),
            'closed' => $listing->close($policy),
            'pending review' => $listing->edit(KindPolicy::of(ListingKind::of('partner'), moderation: Moderation::BeforePublication), Title::of('Wanted'), Criteria::of(), Location::online(), null),
            'rejected' => $listing->edit(KindPolicy::of(ListingKind::of('partner'), moderation: Moderation::BeforePublication), Title::of('Wanted'), Criteria::of(), Location::online(), null)->reject($policy, $reason),
            'expired' => $listing->at($this->clock->now()->modify('+30 days')),
        ];
        $response = $this->responder($policy)->respond($listing, ResponseId::of('r1'), MemberId::of('m1'));

        foreach ($unavailable as $case => $state) {
            try {
                $this->responder($policy)->accept($state, $response);
                self::fail(\sprintf('A response to a %s listing was accepted.', $case));
            } catch (ResponseRefused $responseRefused) {
                self::assertSame(Refusal::NotOpen, $responseRefused->refusal, $case);
            }
        }

        $this->clock->advance(30 * 86400);

        try {
            $this->responder($policy)->accept($listing, $response);
            self::fail('A response to a listing past its expiry was accepted.');
        } catch (ResponseRefused $responseRefused) {
            self::assertSame(Refusal::NotOpen, $responseRefused->refusal);
        }

        self::assertSame(ResponseStatus::Pending, $this->store->responses['r1']->status);
    }

    public function testTheMinimumAgeIsCheckedAgainOnAcceptance(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $listing = $this->listing($policy);
        $responder = $this->responder($policy);
        $this->ages->ages = ['young' => 15, 'adult' => 18];
        $young = $responder->respond($listing, ResponseId::of('r1'), MemberId::of('young'));
        $unknown = $responder->respond($listing, ResponseId::of('r2'), MemberId::of('unknown'));
        $adult = $responder->respond($listing, ResponseId::of('r3'), MemberId::of('adult'));
        $stricter = $listing->edit($policy, Title::of('Wanted'), Criteria::of(), Location::online(), 18);

        foreach ([[$young, Refusal::TooYoung], [$unknown, Refusal::AgeUnknown]] as [$response, $refusal]) {
            try {
                $responder->accept($stricter, $response);
                self::fail('A response below the minimum age was accepted.');
            } catch (ResponseRefused $responseRefused) {
                self::assertSame($refusal, $responseRefused->refusal);
            }
        }

        self::assertSame(ResponseStatus::Pending, $this->store->responses['r1']->status);
        self::assertSame(ResponseStatus::Pending, $this->store->responses['r2']->status);
        self::assertTrue($responder->accept($stricter, $adult)->permitsContact());
    }

    public function testAResponseToAnotherListingIsRefused(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $other = Response::pending(ResponseId::of('r9'), ListingId::of('l9'), MemberId::of('m1'), $this->clock->now());

        $responder = $this->responder($policy);

        foreach ([$responder->accept(...), $responder->decline(...)] as $decision) {
            try {
                $decision($this->listing($policy), $other);
                self::fail('A response to another listing was decided.');
            } catch (ResponseRefused $refused) {
                self::assertSame(Refusal::OtherListing, $refused->refusal);
            }
        }

        self::assertSame([], $this->store->responses);
    }

    public function testADecidedResponseIsNotDecidedAgain(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $listing = $this->listing($policy);
        $responder = $this->responder($policy);
        $declined = $responder->decline($listing, $responder->respond($listing, ResponseId::of('r1'), MemberId::of('m1')));

        $this->expectException(ForbiddenTransition::class);

        $responder->accept($listing, $declined);
    }

    private function responder(KindPolicy $policy): Responder
    {
        return new Responder(new KindPolicies([$policy]), $this->blocks, $this->ages, $this->store, $this->clock);
    }

    private function listing(KindPolicy $policy): Listing
    {
        return Listing::draft(ListingId::of('l1'), $policy, MemberId::of('author'), Title::of('Wanted'), Criteria::of(), Location::online(), null, $this->clock->now())
            ->submit($policy, $this->clock->now());
    }
}
