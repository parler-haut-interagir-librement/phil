<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\StoredResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredResponse::class)]
final class StoredResponseTest extends TestCase
{
    public function testTheResponseIsKeptAsGiven(): void
    {
        $response = StoredResponse::of(202, ['Location' => ['/api/things/1']], '{"id":"1"}');

        self::assertSame(202, $response->status);
        self::assertSame(['Location' => ['/api/things/1']], $response->headers);
        self::assertSame('{"id":"1"}', $response->body);
    }

    #[TestWith([100])]
    #[TestWith([599])]
    public function testTheBoundaryStatusesAreAccepted(int $status): void
    {
        self::assertSame($status, StoredResponse::of($status, [], '')->status);
    }

    #[TestWith([99])]
    #[TestWith([600])]
    public function testAStatusOutsideHttpIsRefused(int $status): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        StoredResponse::of($status, [], '');
    }

    public function testTheHeadersAndTheBodyAreEmptyByDefault(): void
    {
        $response = StoredResponse::of(201);

        self::assertSame([], $response->headers);
        self::assertSame('', $response->body);
    }

    public function testAnOperationThatReturnsNothingKeepsANoContent(): void
    {
        $nothing = StoredResponse::nothing();

        self::assertSame(204, $nothing->status);
        self::assertSame([], $nothing->headers);
        self::assertSame('', $nothing->body);
    }
}
