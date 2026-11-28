<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit;

use Phil\EmailPreview\Sending;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sending::class)]
final class SendingTest extends TestCase
{
    public function testASendingIsNotInBulkByDefault(): void
    {
        $sending = new Sending();

        self::assertFalse($sending->bulk);
        self::assertSame([], $sending->headers);
        self::assertSame([], $sending->recipientTokens);
        self::assertNull($sending->header('List-Unsubscribe'));
    }

    public function testHeadersAreFoundWhateverTheCaseOfTheirName(): void
    {
        $sending = Sending::bulk(['List-Unsubscribe' => '<https://a.test/>'], ['ana@a.test', '', '42']);

        self::assertTrue($sending->bulk);
        self::assertSame(['list-unsubscribe' => '<https://a.test/>'], $sending->headers);
        self::assertSame('<https://a.test/>', $sending->header('LIST-UNSUBSCRIBE'));
        self::assertSame(['ana@a.test', '42'], $sending->recipientTokens);
    }
}
