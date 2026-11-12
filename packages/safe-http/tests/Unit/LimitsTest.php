<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use Phil\SafeHttp\Exception\InvalidLimits;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Limits::class)]
#[CoversClass(ReadRule::class)]
#[CoversClass(InvalidLimits::class)]
final class LimitsTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $limits = new Limits();

        self::assertSame([5.0, 10.0, 1_048_576, 3, 'PhilSafeHttp/0.1'], [$limits->timeout, $limits->maxDuration, $limits->maxBytes, $limits->maxRedirects, $limits->userAgent]);
    }

    public function testTheBoundsThemselvesAreAllowed(): void
    {
        $low = new Limits(0.1, 0.1, 1024, 0, 'a');
        $high = new Limits(60.0, 120.0, 64 * 1024 * 1024, 10, 'b');

        self::assertSame([0.1, 0.1, 1024, 0], [$low->timeout, $low->maxDuration, $low->maxBytes, $low->maxRedirects]);
        self::assertSame([60.0, 120.0, 67_108_864, 10], [$high->timeout, $high->maxDuration, $high->maxBytes, $high->maxRedirects]);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'timeout too short' => [static fn (): Limits => new Limits(timeout: 0.09), 'The timeout is 0.09; it must lie between 0.1 and 60.'];
        yield 'timeout too long' => [static fn (): Limits => new Limits(timeout: 60.01), 'The timeout is 60.01; it must lie between 0.1 and 60.'];
        yield 'duration too short' => [static fn (): Limits => new Limits(maxDuration: 0.09), 'The whole duration is 0.09; it must lie between 0.1 and 120.'];
        yield 'duration too long' => [static fn (): Limits => new Limits(maxDuration: 120.01), 'The whole duration is 120.01; it must lie between 0.1 and 120.'];
        yield 'too few bytes' => [static fn (): Limits => new Limits(maxBytes: 1023), 'The bytes read is 1023; it must lie between 1024 and 67108864.'];
        yield 'too many bytes' => [static fn (): Limits => new Limits(maxBytes: 67_108_865), 'The bytes read is 67108865; it must lie between 1024 and 67108864.'];
        yield 'negative redirects' => [static fn (): Limits => new Limits(maxRedirects: -1), 'The redirects is -1; it must lie between 0 and 10.'];
        yield 'too many redirects' => [static fn (): Limits => new Limits(maxRedirects: 11), 'The redirects is 11; it must lie between 0 and 10.'];
        yield 'empty user agent' => [static fn (): Limits => new Limits(userAgent: ' '), 'The User-Agent is empty, or holds a line break or a control character.'];
        yield 'split user agent' => [static fn (): Limits => new Limits(userAgent: "Bot\r\nX-Evil: 1"), 'The User-Agent is empty, or holds a line break or a control character.'];
        yield 'delete in user agent' => [static fn (): Limits => new Limits(userAgent: "Bot\x7F"), 'The User-Agent is empty, or holds a line break or a control character.'];
        yield 'split accept' => [static fn (): ReadRule => new ReadRule("text/html\n"), 'The Accept is empty, or holds a line break or a control character.'];
        yield 'nul in accept' => [static fn (): ReadRule => new ReadRule("text/html\x00"), 'The Accept is empty, or holds a line break or a control character.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('outOfBounds')]
    public function testLimitsOutOfTheirBoundsAreRefused(callable $build, string $message): void
    {
        try {
            $build();
            self::fail('The limits are refused.');
        } catch (InvalidLimits $invalidLimits) {
            self::assertSame($message, $invalidLimits->getMessage());
        }
    }

    public function testTheRuleOfTheHeadOfAPage(): void
    {
        $rule = ReadRule::htmlHead();

        self::assertSame(['text/html, application/xhtml+xml;q=0.9', '</head'], [$rule->accept, $rule->stopAfter]);
        self::assertTrue($rule->reads('text/html'));
        self::assertTrue($rule->reads('application/xhtml+xml'));
        self::assertFalse($rule->reads('image/png'));
        self::assertFalse($rule->reads(null));
    }

    public function testARuleWithoutTypesReadsAnyBody(): void
    {
        $rule = new ReadRule();

        self::assertSame(['*/*', null], [$rule->accept, $rule->stopAfter]);
        self::assertTrue($rule->reads('image/png'));
        self::assertTrue($rule->reads(null));
    }
}
