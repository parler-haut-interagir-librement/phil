<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Audience;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Exception\InvalidPolicy;
use Phil\ContentAccess\WhenDenied;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessPolicy::class)]
#[CoversClass(InvalidPolicy::class)]
final class AccessPolicyTest extends TestCase
{
    public static function rule(): Rule
    {
        return new Rule(new Leaf('access.member', []), AccessConditions::types());
    }

    public function testThePublicPolicyHoldsNothingElse(): void
    {
        $policy = AccessPolicy::public();

        self::assertSame(Audience::Public, $policy->audience);
        self::assertNull($policy->rule);
        self::assertNull($policy->publicFrom);
        self::assertFalse($policy->preview);
        self::assertSame(WhenDenied::Locked, $policy->whenDenied);
        self::assertTrue($policy->isPublicAt(new DateTimeImmutable('1970-01-01')));
    }

    public function testTheWithersKeepEveryOtherMember(): void
    {
        $rule = self::rule();
        $from = new DateTimeImmutable('2026-12-01T00:00:00+00:00');

        $policy = AccessPolicy::restricted($rule)->withPublicFrom($from)->withPreview();

        self::assertSame(Audience::Restricted, $policy->audience);
        self::assertSame($rule, $policy->rule);
        self::assertSame($from, $policy->publicFrom);
        self::assertTrue($policy->preview);
        self::assertSame(WhenDenied::Locked, $policy->whenDenied);

        $hidden = $policy->withPreview(false)->withWhenDenied(WhenDenied::Hidden);

        self::assertSame($rule, $hidden->rule);
        self::assertSame($from, $hidden->publicFrom);
        self::assertFalse($hidden->preview);
        self::assertSame(WhenDenied::Hidden, $hidden->whenDenied);

        $again = $hidden->withPublicFrom(null);

        self::assertNull($again->publicFrom);
        self::assertSame(WhenDenied::Hidden, $again->withPublicFrom($from)->whenDenied);
        self::assertTrue($policy->withWhenDenied(WhenDenied::Locked)->preview);
        self::assertSame($from, $policy->withWhenDenied(WhenDenied::Locked)->publicFrom);
    }

    public function testAnEarlyAccessEndsAtItsInstant(): void
    {
        $policy = AccessPolicy::members()->withPublicFrom(new DateTimeImmutable('2026-12-01T00:00:00+00:00'));

        self::assertSame(Audience::Members, $policy->audience);
        self::assertFalse($policy->isPublicAt(new DateTimeImmutable('2026-11-30T23:59:59+00:00')));
        self::assertTrue($policy->isPublicAt(new DateTimeImmutable('2026-12-01T00:00:00+00:00')));
        self::assertTrue($policy->isPublicAt(new DateTimeImmutable('2026-11-30T20:00:00-05:00')));
        self::assertFalse(AccessPolicy::members()->isPublicAt(new DateTimeImmutable('2100-01-01')));
    }

    /**
     * @return iterable<string, array{Closure(): AccessPolicy, string}>
     */
    public static function contradictions(): iterable
    {
        $from = new DateTimeImmutable('2026-12-01');

        yield 'a public early access' => [static fn (): AccessPolicy => AccessPolicy::public()->withPublicFrom($from), 'a public content has no early access, preview or hiding'];
        yield 'a public preview' => [static fn (): AccessPolicy => AccessPolicy::public()->withPreview(), 'a public content has no early access, preview or hiding'];
        yield 'a hidden public content' => [static fn (): AccessPolicy => AccessPolicy::public()->withWhenDenied(WhenDenied::Hidden), 'a public content has no early access, preview or hiding'];
        yield 'a hidden preview' => [static fn (): AccessPolicy => AccessPolicy::members()->withPreview()->withWhenDenied(WhenDenied::Hidden), 'a hidden content shows no preview'];
        yield 'a preview of a hidden content' => [static fn (): AccessPolicy => AccessPolicy::members()->withWhenDenied(WhenDenied::Hidden)->withPreview(), 'a hidden content shows no preview'];
    }

    /**
     * @param Closure(): AccessPolicy $build
     */
    #[DataProvider('contradictions')]
    public function testAContradictionIsRefused(Closure $build, string $why): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The access policy is contradictory: '.$why.'.');

        $build();
    }

    public function testAPublicContentThatShowsAPreviewOfFalseIsStillPublic(): void
    {
        self::assertFalse(AccessPolicy::public()->withPreview(false)->preview);
        self::assertSame(WhenDenied::Locked, AccessPolicy::public()->withWhenDenied(WhenDenied::Locked)->whenDenied);
        self::assertNull(AccessPolicy::public()->withPublicFrom(null)->publicFrom);
    }
}
