<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\ContentAccess\Access;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Decision;
use Phil\ContentAccess\PreviewCut;
use Phil\ContentAccess\Reason;
use Phil\ContentAccess\StructuredData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreviewCut::class)]
#[CoversClass(StructuredData::class)]
final class PreviewAndStructuredDataTest extends TestCase
{
    private const array INTRO = ['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => 'Intro']];

    private const array CUT = ['id' => 'b', 'type' => 'access-cut', 'data' => []];

    private const array BODY = ['id' => 'c', 'type' => 'paragraph', 'data' => ['text' => 'Reserved']];

    private const array MORE = ['id' => 'd', 'type' => 'access-cut', 'data' => []];

    private const array END = ['id' => 'e', 'type' => 'paragraph', 'data' => ['text' => 'End']];

    private function decision(Access $access): Decision
    {
        return new Decision($access, Reason::Member, true);
    }

    public function testTheWholeContentLosesOnlyItsCuts(): void
    {
        $cut = new PreviewCut();

        self::assertSame('access-cut', $cut->cutType);
        self::assertSame([self::INTRO, self::BODY, self::END], $cut->visibleBlocks($this->decision(Access::Full), [self::INTRO, self::CUT, self::BODY, self::MORE, self::END]));
    }

    public function testAPreviewStopsAtTheFirstCut(): void
    {
        $cut = new PreviewCut();
        $blocks = [self::INTRO, self::CUT, self::BODY, self::MORE, self::END];

        self::assertSame([self::INTRO], $cut->visibleBlocks($this->decision(Access::Preview), $blocks));
        self::assertSame([self::INTRO], $cut->publicPart($blocks));
        self::assertSame([], $cut->publicPart([self::CUT, self::BODY]));
    }

    public function testADocumentWithoutCutHasNoPublicPart(): void
    {
        $cut = new PreviewCut();

        self::assertSame([], $cut->publicPart([self::INTRO, self::BODY]));
        self::assertSame([], $cut->visibleBlocks($this->decision(Access::Preview), [self::INTRO, self::BODY]));
        self::assertFalse($cut->hasCut([self::INTRO, self::BODY]));
        self::assertTrue($cut->hasCut([self::INTRO, self::CUT]));
        self::assertFalse($cut->hasCut([]));
    }

    public function testALockedOrHiddenContentSendsNoBlock(): void
    {
        $cut = new PreviewCut();

        self::assertSame([], $cut->visibleBlocks($this->decision(Access::Locked), [self::INTRO, self::CUT, self::BODY]));
        self::assertSame([], $cut->visibleBlocks($this->decision(Access::Hidden), [self::INTRO, self::CUT, self::BODY]));
    }

    public function testTheCutTypeIsConfigurable(): void
    {
        $cut = new PreviewCut('paywall');
        $paywall = ['type' => 'paywall'];

        self::assertSame([self::INTRO, self::CUT], $cut->publicPart([self::INTRO, self::CUT, $paywall, self::BODY]));
        self::assertSame([['data' => []], self::INTRO], $cut->visibleBlocks($this->decision(Access::Full), [['data' => []], $paywall, self::INTRO]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1cut'];
        yield 'a space' => ['access cut'];
        yield 'a newline after' => ["cut\n"];
        yield 'too long' => [str_repeat('a', 65)];
    }

    #[DataProvider('badTypes')]
    public function testABadCutTypeIsRefused(string $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The type of the cut block is a letter, then letters, digits, "_" or "-", 64 bytes at most.');

        new PreviewCut($type);
    }

    public function testTheLongestCutTypeIsAccepted(): void
    {
        self::assertSame(64, \strlen(new PreviewCut('a'.str_repeat('_', 63))->cutType));
    }

    public function testAPublicContentIsAccessibleForFree(): void
    {
        self::assertSame(['isAccessibleForFree' => true], StructuredData::of(AccessPolicy::public(), new DateTimeImmutable()));

        $ended = AccessPolicy::members()->withPublicFrom(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        self::assertSame(['isAccessibleForFree' => true], StructuredData::of($ended, new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    }

    public function testAReservedContentNamesItsReservedPart(): void
    {
        self::assertSame(
            ['isAccessibleForFree' => false, 'hasPart' => [['@type' => 'WebPageElement', 'isAccessibleForFree' => false, 'cssSelector' => '.reserved']]],
            StructuredData::of(AccessPolicy::members(), new DateTimeImmutable()),
        );
        self::assertSame(
            '#body > .paid',
            StructuredData::of(AccessPolicy::members()->withPublicFrom(new DateTimeImmutable('2100-01-01')), new DateTimeImmutable(), '#body > .paid')['hasPart'][0]['cssSelector'] ?? null,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badSelectors(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'a newline' => [".a\n.b"];
        yield 'a delete character' => [".a\x7F"];
        yield 'a tag' => ['</script>'];
        yield 'an opening bracket' => ['.a<'];
    }

    #[DataProvider('badSelectors')]
    public function testABadSelectorIsRefused(string $selector): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The selector of the reserved part is empty, longer than 255 bytes, or holds a control character or a "<".');

        StructuredData::of(AccessPolicy::public(), new DateTimeImmutable(), $selector);
    }

    public function testTheLongestSelectorIsAccepted(): void
    {
        self::assertArrayHasKey('hasPart', StructuredData::of(AccessPolicy::members(), new DateTimeImmutable(), str_repeat('a', 255)));
    }
}
