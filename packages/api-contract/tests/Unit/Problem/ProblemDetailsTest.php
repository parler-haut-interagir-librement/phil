<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\Problem;

use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContract\Problem\ProblemDetails;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProblemDetails::class)]
final class ProblemDetailsTest extends TestCase
{
    private const string TYPE = 'https://example.com/errors/out-of-credit';

    public function testAnAbsentMemberIsLeftOutRatherThanWrittenAsNull(): void
    {
        self::assertSame(
            ['type' => 'about:blank', 'status' => 404],
            ProblemDetails::create(404)->toArray(),
        );
    }

    public function testEveryMemberAndExtensionIsWritten(): void
    {
        $problem = ProblemDetails::create(403, self::TYPE, 'Out of credit', 'Your balance is 30.', '/account/12345/msgs/abc', ['balance' => 30]);

        self::assertSame([
            'type' => self::TYPE,
            'status' => 403,
            'title' => 'Out of credit',
            'detail' => 'Your balance is 30.',
            'instance' => '/account/12345/msgs/abc',
            'balance' => 30,
        ], $problem->toArray());
        self::assertTrue($problem->hasType(self::TYPE));
        self::assertFalse($problem->isBlank());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonErrorStatuses(): iterable
    {
        yield 'success' => [200];
        yield 'redirect' => [302];
        yield 'just below' => [399];
        yield 'just above' => [600];
    }

    #[DataProvider('nonErrorStatuses')]
    public function testAProblemCarriesAnErrorStatus(int $status): void
    {
        $this->expectException(InvalidContractValue::class);

        ProblemDetails::create($status);
    }

    public function testTheBoundaryStatusesAreErrors(): void
    {
        self::assertSame(400, ProblemDetails::create(400)->status);
        self::assertSame(599, ProblemDetails::create(599)->status);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableTypes(): iterable
    {
        yield 'relative reference' => ['/errors/out-of-credit'];
        yield 'empty' => [''];
        yield 'with a space' => ['https://example.com/a b'];
        yield 'no scheme' => ['example.com/errors'];
        yield 'trailing newline' => ["https://example.com/errors\n"];
    }

    #[DataProvider('unusableTypes')]
    public function testTheServerWritesOnlyAnAbsoluteType(string $type): void
    {
        $this->expectException(InvalidContractValue::class);

        ProblemDetails::create(400, $type);
    }

    public function testAnExtensionMayNotShadowAStandardMember(): void
    {
        $this->expectException(InvalidContractValue::class);
        $this->expectExceptionMessageIsOrContains('"detail" would shadow');

        ProblemDetails::create(400, extensions: ['detail' => 'x']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badExtensionNames(): iterable
    {
        yield 'too short' => ['ab'];
        yield 'starts with a digit' => ['1abc'];
        yield 'hyphen' => ['out-of'];
    }

    #[DataProvider('badExtensionNames')]
    public function testAnExtensionIsNamedAsTheRfcRecommends(string $name): void
    {
        $this->expectException(InvalidContractValue::class);

        ProblemDetails::create(400, extensions: [$name => 1]);
    }

    public function testAClientReadsWhatTheServerWrote(): void
    {
        $written = ProblemDetails::create(409, self::TYPE, 'Conflict', extensions: ['retry_after' => 3]);

        self::assertEquals($written, ProblemDetails::fromArray($written->toArray(), 409));
    }

    /**
     * RFC 9457 §3.1: a member whose value has the wrong JSON type is ignored, not fatal.
     */
    public function testAMemberOfTheWrongTypeIsIgnored(): void
    {
        $problem = ProblemDetails::fromArray(['type' => 42, 'title' => ['x'], 'detail' => null, 'instance' => false], 422);

        self::assertTrue($problem->isBlank());
        self::assertNull($problem->title);
        self::assertNull($problem->detail);
        self::assertNull($problem->instance);
    }

    /**
     * An unusable type leaves the client with the status to go on, which is the fallback the contract names.
     */
    public function testARelativeTypeIsReadAsBlank(): void
    {
        self::assertTrue(ProblemDetails::fromArray(['type' => '/errors/x'], 400)->isBlank());
    }

    public function testTheStatusMemberTellsWhatTheOriginSent(): void
    {
        self::assertSame(429, ProblemDetails::fromArray(['status' => 429], 502)->status);
    }

    public function testTheResponseStatusIsTheFallback(): void
    {
        self::assertSame(503, ProblemDetails::fromArray(['status' => '429'], 503)->status);
        self::assertSame(503, ProblemDetails::fromArray(['status' => 200], 503)->status);
    }

    public function testADocumentWithNoErrorStatusAnywhereIsNotAProblem(): void
    {
        $this->expectException(InvalidContractValue::class);

        ProblemDetails::fromArray(['type' => self::TYPE], 200);
    }

    /**
     * The client is tolerant: an extension the server named badly is still handed over.
     */
    public function testUnknownMembersAreKeptAsExtensions(): void
    {
        $problem = ProblemDetails::fromArray(['status' => 400, 'ab' => 1, 'invalid_params' => [['name' => 'age']], 0 => 'x'], 400);

        self::assertSame(['ab' => 1, 'invalid_params' => [['name' => 'age']]], $problem->extensions);
    }
}
