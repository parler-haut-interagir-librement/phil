<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Measure;

use Override;
use Phil\AnalyticsBundle\Application\Contract\OutboundLinkDirectory;
use Phil\AnalyticsBundle\Application\Measure\MeasureCounters;
use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\ClickTracking;
use Phil\AnalyticsBundle\Domain\Measure\ClosedList;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredDimension;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;
use Phil\AnalyticsBundle\Domain\Measure\Goal;
use Phil\AnalyticsBundle\Domain\Measure\MeasureEvent;
use Phil\AnalyticsBundle\Domain\Measure\MeasurePlan;
use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoute;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasureCounters::class)]
final class MeasureCountersTest extends TestCase
{
    private MeasureCounters $counters;

    private UtcDay $day;

    protected function setUp(): void
    {
        $this->counters = new MeasureCounters(new class implements OutboundLinkDirectory {
            #[Override]
            public function find(SiteId $site, string $reference): ?DeclaredOutboundLink
            {
                return 'l1' === $reference ? DeclaredOutboundLink::of('l1', 'alice') : null;
            }
        });
        $this->day = UtcDay::fromString('2026-11-09');
    }

    private function site(?MeasurePlan $plan = null): CollectionSite
    {
        return CollectionSite::of(
            SiteId::fromString('s'),
            [Origin::fromString('https://a.example')],
            DeclaredRoutes::of([DeclaredRoute::of('home', '/'), DeclaredRoute::of('thanks', '/thanks')]),
            SiteStatus::Active,
            $plan,
        );
    }

    /**
     * @param list<CounterKey> $keys
     *
     * @return list<string>
     */
    private function strings(array $keys): array
    {
        return array_map(static fn (CounterKey $key): string => $key->site.'/'.$key->day.'/'.$key->metric.'/'.$key->dimension.'/'.$key->value, $keys);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function event(array $payload, ?MeasurePlan $plan = null, ReferralKind $referral = ReferralKind::Direct): array
    {
        return array_map(
            static fn (string $key): string => substr($key, \strlen('s/2026-11-09/')),
            $this->strings($this->counters->ofEvent($this->site($plan), $this->day, $referral, $payload)),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function pageView(array $payload, ?MeasurePlan $plan = null, ReferralKind $referral = ReferralKind::Direct, string $route = 'thanks'): array
    {
        return array_map(
            static fn (string $key): string => substr($key, \strlen('s/2026-11-09/')),
            $this->strings($this->counters->ofPageView($this->site($plan), $this->day, $route, $referral, $payload)),
        );
    }

    private function experiment(?Goal $goal = null): MeasurePlan
    {
        return MeasurePlan::of(
            clickNames: ClosedList::of(['cta']),
            forms: ClosedList::of(['contact']),
            goals: [$goal ?? Goal::onRoute('done', 'thanks')],
            dimensions: [DeclaredDimension::of('variant', ClosedList::of(['a', 'b']))],
        );
    }

    public function testAPageViewCountsItsTransition(): void
    {
        self::assertSame(['transitions/route_pair/home>thanks'], $this->pageView(['from' => '/']));
    }

    public function testAPageViewWithoutAnUsablePreviousPathCountsNoTransition(): void
    {
        self::assertSame([], $this->pageView([]));
        self::assertSame([], $this->pageView(['from' => 42]));
        self::assertSame([], $this->pageView(['from' => '/unknown']));
    }

    public function testAVisitCountsTheDeclaredDimensionsAndTheGoal(): void
    {
        self::assertSame(
            ['pageviews/variant/b', 'visits/variant/b', 'goals/goal/done', 'goals.done/variant/b'],
            $this->pageView(['dimensions' => ['variant' => 'b', 'forged' => 'x']], $this->experiment()),
        );
    }

    public function testEveryDeclaredDimensionIsCounted(): void
    {
        $plan = MeasurePlan::of(dimensions: [
            DeclaredDimension::of('variant', ClosedList::of(['a', 'b'])),
            DeclaredDimension::of('theme', ClosedList::of(['dark', 'light'])),
        ]);

        self::assertSame(
            ['pageviews/variant/a', 'visits/variant/a', 'pageviews/theme/dark', 'visits/theme/dark'],
            $this->pageView(['dimensions' => ['theme' => 'dark', 'variant' => 'a']], $plan),
        );
    }

    public function testAnInternalPageViewIsNotAVisit(): void
    {
        self::assertSame(
            ['pageviews/variant/a', 'goals/goal/done', 'goals.done/variant/a'],
            $this->pageView(['dimensions' => ['variant' => 'a']], $this->experiment(), ReferralKind::Internal),
        );
    }

    public function testARouteThatIsNoGoalCountsNone(): void
    {
        self::assertSame([], $this->pageView([], $this->experiment(), route: 'home'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function junkDimensions(): iterable
    {
        yield 'not a map' => [['dimensions' => 'x']];
        yield 'not declared' => [['dimensions' => ['other' => 'a']]];
        yield 'not listed' => [['dimensions' => ['variant' => 'z']]];
        yield 'not a string' => [['dimensions' => ['variant' => 1]]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('junkDimensions')]
    public function testJunkDimensionsCountNothing(array $payload): void
    {
        self::assertSame(['goals/goal/done'], $this->pageView($payload, $this->experiment()));
    }

    public function testADeclaredClick(): void
    {
        self::assertSame(['clicks/name/cta'], $this->event(['event' => 'click', 'name' => 'cta'], $this->experiment()));
        self::assertSame([], $this->event(['event' => 'click', 'name' => 'forged'], $this->experiment()));
        self::assertSame([], $this->event(['event' => 'click', 'name' => 1], $this->experiment()));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function clickEvents(): iterable
    {
        yield 'a named click' => ['click', ['name' => 'cta']];
        yield 'a download' => ['download', ['extension' => 'pdf']];
        yield 'an outbound link' => ['outbound', ['host' => 'b.example']];
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('clickEvents')]
    public function testClicksOffCountsNoClick(string $event, array $fields): void
    {
        self::assertSame([], $this->event(['event' => $event, ...$fields], MeasurePlan::of(ClickTracking::Off, ClosedList::of(['cta']))));
    }

    public function testClicksOffStillCountsTheRest(): void
    {
        self::assertSame(['events/form/contact'], $this->event(['event' => 'form_submit', 'form' => 'contact'], MeasurePlan::of(ClickTracking::Off, forms: ClosedList::of(['contact']))));
    }

    public function testADownloadOfAListedExtension(): void
    {
        self::assertSame(['events/download/pdf'], $this->event(['event' => 'download', 'extension' => 'pdf']));
        self::assertSame([], $this->event(['event' => 'download', 'extension' => 'html']));
    }

    public function testADeclaredForm(): void
    {
        self::assertSame(['events/form/contact'], $this->event(['event' => 'form_submit', 'form' => 'contact'], MeasurePlan::of(forms: ClosedList::of(['contact']))));
        self::assertSame([], $this->event(['event' => 'form_submit', 'form' => 'contact']));
    }

    public function testAPageNotFoundIsCountedBySourceOnly(): void
    {
        self::assertSame(['events/not_found/internal'], $this->event(['event' => 'not_found', 'path' => '/a/secret'], referral: ReferralKind::Internal));
    }

    public function testAnEventReachesItsGoalWithItsVariant(): void
    {
        $plan = $this->experiment(Goal::onEvent('contact', MeasureEvent::FormSubmit, 'contact'));

        self::assertSame(
            ['events/form/contact', 'goals/goal/contact', 'goals.contact/variant/b'],
            $this->event(['event' => 'form_submit', 'form' => 'contact', 'dimensions' => ['variant' => 'b']], $plan),
        );
    }

    public function testAnotherEventReachesNoGoal(): void
    {
        $plan = $this->experiment(Goal::onEvent('contact', MeasureEvent::FormSubmit, 'other'));

        self::assertSame(['events/form/contact'], $this->event(['event' => 'form_submit', 'form' => 'contact'], $plan));
    }

    public function testAnOutboundLinkIsCountedByHost(): void
    {
        self::assertSame(['outbound/host/b.example'], $this->event(['event' => 'outbound', 'host' => 'B.example']));
    }

    public function testADeclaredOutboundLinkIsCountedToo(): void
    {
        self::assertSame(['outbound/host/b.example', 'outbound_link/link/l1'], $this->event(['event' => 'outbound', 'host' => 'b.example', 'link' => 'l1']));
    }

    public function testAForgedLinkReferenceCountsTheHostOnly(): void
    {
        self::assertSame(['outbound/host/b.example'], $this->event(['event' => 'outbound', 'host' => 'b.example', 'link' => 'forged']));
        self::assertSame(['outbound/host/b.example'], $this->event(['event' => 'outbound', 'host' => 'b.example', 'link' => 1]));
    }

    public function testAnOutboundEventWithoutAHostCountsNothing(): void
    {
        self::assertSame([], $this->event(['event' => 'outbound']));
        self::assertSame([], $this->event(['event' => 'outbound', 'host' => 'a.example', 'link' => 'l1']));
    }

    public function testASearch(): void
    {
        self::assertSame(['search/term/dice', 'search/outcome/found'], $this->event(['event' => 'search', 'term' => 'DICE', 'results' => true]));
        self::assertSame(['search/term/dice', 'search/outcome/none'], $this->event(['event' => 'search', 'term' => 'dice', 'results' => false]));
    }

    public function testASearchThatNamesSomeoneKeepsOnlyItsOutcome(): void
    {
        self::assertSame(['search/outcome/none'], $this->event(['event' => 'search', 'term' => 'alice@example.org', 'results' => false]));
        self::assertSame([], $this->event(['event' => 'search', 'results' => 'yes']));
    }

    public function testEngagementInBands(): void
    {
        self::assertSame(['engagement/scroll/75', 'engagement/active/30s_1m'], $this->event(['event' => 'engagement', 'scroll' => 75, 'active' => 42]));
        self::assertSame(['engagement/scroll/0', 'engagement/active/under_10s'], $this->event(['event' => 'engagement', 'scroll' => 0, 'active' => 0]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function junkEngagement(): iterable
    {
        yield 'off the bands' => [['scroll' => 33]];
        yield 'a text' => [['scroll' => '75', 'active' => '42']];
        yield 'negative' => [['active' => -1]];
        yield 'nothing' => [[]];
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('junkEngagement')]
    public function testJunkEngagementCountsNothing(array $fields): void
    {
        self::assertSame([], $this->event(['event' => 'engagement', ...$fields]));
    }

    public function testAnEventOutOfTheListCountsNothing(): void
    {
        self::assertSame([], $this->event(['event' => 'forged']));
        self::assertSame([], $this->event(['event' => 1]));
        self::assertSame([], $this->event([]));
    }

    public function testTheCountersBelongToTheSiteAndTheDay(): void
    {
        self::assertSame(
            ['s/2026-11-09/events/not_found/direct'],
            $this->strings($this->counters->ofEvent($this->site(), $this->day, ReferralKind::Direct, ['event' => 'not_found'])),
        );
    }
}
