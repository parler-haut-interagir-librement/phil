<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Measure;

use Phil\AnalyticsBundle\Application\Contract\OutboundLinkDirectory;
use Phil\AnalyticsBundle\Domain\Measure\ActiveTime;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;
use Phil\AnalyticsBundle\Domain\Measure\Goal;
use Phil\AnalyticsBundle\Domain\Measure\MeasureEvent;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Measure\OutboundHost;
use Phil\AnalyticsBundle\Domain\Measure\RouteTransition;
use Phil\AnalyticsBundle\Domain\Measure\ScrollDepth;
use Phil\AnalyticsBundle\Domain\Measure\SearchTerm;
use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;

/**
 * The counters one admitted page view or event advances, read from what the page sent.
 *
 * **The body comes from the client and can hold anything.** Each field is checked for its type and against the site's
 * closed lists: what does not fit counts nothing. Nothing here keeps a visit, a visitor or an instant finer than the
 * day: each counter stands alone.
 */
final readonly class MeasureCounters
{
    public function __construct(
        private OutboundLinkDirectory $links,
    ) {
    }

    /**
     * What a page view advances beyond its own route, device and country counters: its transition, its goals and the
     * dimensions the site declares.
     *
     * @param string               $route   the declared route of the page view
     * @param array<string, mixed> $payload the decoded body
     *
     * @return list<CounterKey>
     */
    public function ofPageView(CollectionSite $site, UtcDay $day, string $route, ReferralKind $referral, array $payload): array
    {
        $keys = [];
        $previous = $this->text($payload, 'from');
        $transition = null === $previous ? null : RouteTransition::valueOf($site->routes, $route, $previous);

        if (null !== $transition) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::TRANSITIONS, 'route_pair', $transition);
        }

        $variants = $this->declaredValues($site, $payload);

        foreach ($variants as $dimension => $value) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::PAGEVIEWS, $dimension, $value);

            if ($referral->isVisit()) {
                $keys[] = CounterKey::of($site->site, $day, MeasureNames::VISITS, $dimension, $value);
            }
        }

        foreach ($site->plan->goals as $goal) {
            if ($goal->isReachedByRoute($route)) {
                array_push($keys, ...$this->goalKeys($site, $day, $goal, $variants));
            }
        }

        return $keys;
    }

    /**
     * What an event the page reported advances.
     *
     * @param array<string, mixed> $payload the decoded body
     *
     * @return list<CounterKey>
     */
    public function ofEvent(CollectionSite $site, UtcDay $day, ReferralKind $referral, array $payload): array
    {
        $event = MeasureEvent::tryFrom($this->text($payload, 'event') ?? '');

        if (null === $event || ($event->isClick() && !$site->plan->clicks->counts())) {
            return [];
        }

        return match ($event) {
            MeasureEvent::Click => $this->listed($site, $day, $event, MeasureNames::CLICKS, 'name', $site->plan->clickNames->has(...), $this->text($payload, 'name'), $payload),
            MeasureEvent::Download => $this->listed($site, $day, $event, MeasureNames::EVENTS, 'download', $site->plan->downloads->has(...), $this->text($payload, 'extension'), $payload),
            MeasureEvent::FormSubmit => $this->listed($site, $day, $event, MeasureNames::EVENTS, 'form', $site->plan->forms->has(...), $this->text($payload, 'form'), $payload),
            MeasureEvent::NotFound => [CounterKey::of($site->site, $day, MeasureNames::EVENTS, 'not_found', $referral->value)],
            MeasureEvent::Outbound => $this->outbound($site, $day, $payload),
            MeasureEvent::Search => $this->search($site, $day, $payload),
            MeasureEvent::Engagement => $this->engagement($site, $day, $payload),
        };
    }

    /**
     * A value of a closed list, and the goals it reaches.
     *
     * @param callable(string): bool $isListed
     * @param array<string, mixed>   $payload
     *
     * @return list<CounterKey>
     */
    private function listed(CollectionSite $site, UtcDay $day, MeasureEvent $event, string $metric, string $dimension, callable $isListed, ?string $value, array $payload): array
    {
        if (null === $value || !$isListed($value)) {
            return [];
        }

        $keys = [CounterKey::of($site->site, $day, $metric, $dimension, $value)];
        $variants = $this->declaredValues($site, $payload);

        foreach ($site->plan->goals as $goal) {
            if ($goal->isReachedByEvent($event, $value)) {
                array_push($keys, ...$this->goalKeys($site, $day, $goal, $variants));
            }
        }

        return $keys;
    }

    /**
     * A goal reached: its counter, and one per declared dimension under the metric `goal.<name>`, so that the
     * variants of an experiment convert side by side.
     *
     * @param array<string, string> $variants
     *
     * @return list<CounterKey>
     */
    private function goalKeys(CollectionSite $site, UtcDay $day, Goal $goal, array $variants): array
    {
        $keys = [CounterKey::of($site->site, $day, MeasureNames::GOALS, 'goal', $goal->name)];

        foreach ($variants as $dimension => $value) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::GOALS.'.'.$goal->name, $dimension, $value);
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<CounterKey>
     */
    private function outbound(CollectionSite $site, UtcDay $day, array $payload): array
    {
        $host = OutboundHost::valueOf($this->text($payload, 'host') ?? '', $site->origins);

        if (null === $host) {
            return [];
        }

        $keys = [CounterKey::of($site->site, $day, MeasureNames::OUTBOUND, 'host', $host)];
        $reference = $this->text($payload, 'link');
        $link = null === $reference ? null : $this->links->find($site->site, $reference);

        if ($link instanceof DeclaredOutboundLink) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::OUTBOUND_LINK, 'link', $link->reference);
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<CounterKey>
     */
    private function search(CollectionSite $site, UtcDay $day, array $payload): array
    {
        $keys = [];
        $term = SearchTerm::valueOf($this->text($payload, 'term') ?? '');

        if (null !== $term) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::SEARCH, 'term', $term);
        }

        $results = $payload['results'] ?? null;

        if (\is_bool($results)) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::SEARCH, 'outcome', $results ? 'found' : 'none');
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<CounterKey>
     */
    private function engagement(CollectionSite $site, UtcDay $day, array $payload): array
    {
        $keys = [];
        $scroll = $payload['scroll'] ?? null;
        $depth = \is_int($scroll) ? ScrollDepth::tryFrom($scroll) : null;

        if (null !== $depth) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::ENGAGEMENT, 'scroll', (string) $depth->value);
        }

        $active = $payload['active'] ?? null;

        if (\is_int($active) && $active >= 0) {
            $keys[] = CounterKey::of($site->site, $day, MeasureNames::ENGAGEMENT, 'active', ActiveTime::ofSeconds($active)->value);
        }

        return $keys;
    }

    /**
     * The values of the site's declared dimensions the page sent, each checked against its closed list.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, string> by dimension
     */
    private function declaredValues(CollectionSite $site, array $payload): array
    {
        $sent = $payload['dimensions'] ?? null;
        $sent = \is_array($sent) ? $sent : [];

        $values = [];

        foreach ($site->plan->dimensions as $dimension) {
            $value = $sent[$dimension->name] ?? null;

            if (\is_string($value) && $dimension->values->has($value)) {
                $values[$dimension->name] = $value;
            }
        }

        return $values;
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
