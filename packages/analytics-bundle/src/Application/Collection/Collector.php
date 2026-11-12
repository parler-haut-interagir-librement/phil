<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Collection;

use Phil\AnalyticsBundle\Application\Contract\CollectionProjection;
use Phil\AnalyticsBundle\Application\Contract\CollectionRateLimiter;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Application\Contract\CountryResolver;
use Phil\AnalyticsBundle\Application\Contract\CrawlerDetector;
use Phil\AnalyticsBundle\Application\Measure\MeasureCounters;
use Phil\AnalyticsBundle\Domain\Collection\Admission;
use Phil\AnalyticsBundle\Domain\Collection\AdmissionRule;
use Phil\AnalyticsBundle\Domain\Collection\ClientDigester;
use Phil\AnalyticsBundle\Domain\Device\DeviceClass;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\PrivacySignals;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Psr\Clock\ClockInterface;

/**
 * One collection request, from its origin to the counters, in the fixed order of `doc/collection-and-admission.md`:
 * the site, its status, the limiter, the admission, then the writes. Each step only narrows; nothing later turns a
 * refusal into a write.
 *
 * **The body comes from the client and can hold anything.** It is read up to {@see self::MAX_BODY_BYTES}, decoded to a
 * bounded depth, and each field is checked for its type: junk counts nothing.
 */
final readonly class Collector
{
    /**
     * The longest body read: a page view or an event is a few hundred bytes.
     */
    public const int MAX_BODY_BYTES = 4096;

    /**
     * The deepest body decoded: an object of fields, one of which, `dimensions`, is an object of strings.
     */
    private const int MAX_DEPTH = 3;

    public function __construct(
        private CollectionProjection $projection,
        private CollectionRateLimiter $limiter,
        private ClientDigester $digester,
        private ReferrerClassifier $classifier,
        private AdmissionRule $admission,
        private CrawlerDetector $crawlers,
        private CountryResolver $countries,
        private CounterStore $store,
        private MeasureCounters $measures,
        private ClockInterface $clock,
    ) {
    }

    public function collect(CollectionRequest $request): CollectionOutcome
    {
        if (\strlen($request->body) > self::MAX_BODY_BYTES) {
            return CollectionOutcome::TooLarge;
        }

        $origin = Origin::ofRequest($request->origin, $request->referer);
        $site = $origin instanceof Origin ? $this->projection->siteDeclaring($origin) : null;

        // Refused before the limiter: a site that does not collect spends no budget and leaves no digest.
        if (!$site instanceof CollectionSite || !$site->status->collects()) {
            return CollectionOutcome::Refused;
        }

        try {
            $digest = $this->digester->digestOf($request->clientIp);
        } catch (InvalidValue) {
            return CollectionOutcome::Refused;
        }

        if (!$this->limiter->consume($site->site, $digest)) {
            return CollectionOutcome::OverBudget;
        }

        $payload = $this->payloadOf($request->body);
        $referral = $this->classifier->classify($this->text($payload, 'referrer'), $site->origins);
        $admission = $this->admission->decide(
            $site->status,
            PrivacySignals::fromHeaders($request->doNotTrack, $request->globalPrivacyControl),
            $this->crawlers->isDeclaredCrawler($request->userAgent),
            $referral,
            Verdict::fromSubmitted($this->text($payload, 'verdict')),
        );

        return $this->write($admission, $site, $referral, $request, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function write(Admission $admission, CollectionSite $site, ReferralKind $referral, CollectionRequest $request, array $payload): CollectionOutcome
    {
        if ($admission->isRefusal()) {
            return CollectionOutcome::Refused;
        }

        $day = UtcDay::of($this->clock->now());

        if ($admission->advancesRobotVerdictCounter()) {
            $this->store->increment(CounterKey::of($site->site, $day, MeasureNames::ROBOT_VERDICTS, 'verdict', Verdict::Robot->value));

            return CollectionOutcome::Counted;
        }

        if (!$admission->advancesAudience()) {
            return CollectionOutcome::NotCounted;
        }

        $keys = null === $this->text($payload, 'event')
            ? $this->pageViewKeys($site, $day, $referral, $request, $payload)
            : $this->measures->ofEvent($site, $day, $referral, $payload);

        foreach ($keys as $key) {
            $this->store->increment($key);
        }

        return CollectionOutcome::Counted;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<CounterKey>
     */
    private function pageViewKeys(CollectionSite $site, UtcDay $day, ReferralKind $referral, CollectionRequest $request, array $payload): array
    {
        $route = $site->routes->normalize($this->text($payload, 'path') ?? '');
        $keys = [];

        foreach ([
            'route' => $route,
            'device' => DeviceClass::fromUserAgent($request->userAgent)->value,
            'country' => $this->countries->countryOf($request->clientIp)->toString(),
        ] as $dimension => $value) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::PAGEVIEWS, $dimension, $value);
        }

        if ($referral->isVisit()) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::VISITS, 'source', $referral->value);
        }

        return [...$keys, ...$this->measures->ofPageView($site, $day, $route, $referral, $payload)];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadOf(string $body): array
    {
        $payload = json_decode($body, true, self::MAX_DEPTH + 1);

        if (!\is_array($payload)) {
            return [];
        }

        return array_filter($payload, is_string(...), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function text(array $payload, string $field): ?string
    {
        $value = $payload[$field] ?? null;

        return \is_string($value) ? $value : null;
    }
}
