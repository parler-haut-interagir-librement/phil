<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use DateTimeImmutable;
use Phil\Listings\Exception\ForbiddenTransition;
use Phil\Listings\ListingId;
use Phil\Listings\MemberId;
use Phil\Listings\Response;
use Phil\Listings\ResponseId;
use Phil\Listings\ResponseStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testAResponseWaitsForTheAuthor(): void
    {
        $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $response = Response::pending(ResponseId::of('r1'), ListingId::of('l1'), MemberId::of('m1'), $at);

        self::assertSame('r1', $response->id->value);
        self::assertSame('l1', $response->listing->value);
        self::assertSame('m1', $response->respondent->value);
        self::assertSame(ResponseStatus::Pending, $response->status);
        self::assertSame($at, $response->sentAt);
        self::assertNull($response->decidedAt);
        self::assertTrue($response->isOpen());
        self::assertFalse($response->permitsContact());
    }

    public function testAnAcceptedResponsePermitsContactUntilWithdrawn(): void
    {
        $sent = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $decided = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
        $accepted = $this->pending()->accept($decided);

        self::assertSame(ResponseStatus::Accepted, $accepted->status);
        self::assertSame($decided, $accepted->decidedAt);
        self::assertTrue($accepted->isOpen());
        self::assertTrue($accepted->permitsContact());
        self::assertSame('r1', $accepted->id->value);
        self::assertSame('l1', $accepted->listing->value);
        self::assertSame('m1', $accepted->respondent->value);
        self::assertEquals($sent, $accepted->sentAt);

        $withdrawn = $accepted->withdraw($decided);

        self::assertSame(ResponseStatus::Withdrawn, $withdrawn->status);
        self::assertFalse($withdrawn->isOpen());
        self::assertFalse($withdrawn->permitsContact());
    }

    public function testADeclinedResponseIsClosed(): void
    {
        $declined = $this->pending()->decline(new DateTimeImmutable('2026-01-02T00:00:00+00:00'));

        self::assertSame(ResponseStatus::Declined, $declined->status);
        self::assertFalse($declined->isOpen());
        self::assertFalse($declined->permitsContact());
        self::assertSame(ResponseStatus::Withdrawn, $this->pending()->withdraw(new DateTimeImmutable())->status);

        $this->expectExceptionObject(ForbiddenTransition::because('A response that is declined cannot become accepted.'));

        $declined->accept(new DateTimeImmutable());
    }

    public function testADecidedResponseIsNotDecidedAgain(): void
    {
        $accepted = $this->pending()->accept(new DateTimeImmutable());

        try {
            $accepted->decline(new DateTimeImmutable());
            self::fail('An accepted response was declined.');
        } catch (ForbiddenTransition) {
        }

        $this->expectException(ForbiddenTransition::class);

        $accepted->withdraw(new DateTimeImmutable())->withdraw(new DateTimeImmutable());
    }

    public function testAStoredResponseIsRestored(): void
    {
        $at = new DateTimeImmutable('2026-01-03T00:00:00+00:00');
        $response = Response::restore(ResponseId::of('r2'), ListingId::of('l2'), MemberId::of('m2'), ResponseStatus::Declined, $at, $at);

        self::assertSame(ResponseStatus::Declined, $response->status);
        self::assertSame($at, $response->decidedAt);
        self::assertSame('r2', $response->id->value);
    }

    private function pending(): Response
    {
        return Response::pending(ResponseId::of('r1'), ListingId::of('l1'), MemberId::of('m1'), new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    }
}
