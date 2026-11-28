<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Support;

use Phil\AudienceSegments\Contract\Participation;
use Phil\AudienceSegments\Contract\PeopleTags;
use Phil\AudienceSegments\Contract\Purchases;
use Phil\AudienceSegments\Contract\Supporters;
use Phil\AudienceSegments\PurchaseFilter;

/**
 * Every fact port in memory, answering from what it is given, and remembering the questions asked.
 */
final class MemoryFacts implements Participation, Purchases, Supporters, PeopleTags
{
    /** @var array<string, array<string, list<string>>> member => event => ticket types */
    public array $tickets = [];

    /** @var array<string, list<string>> */
    public array $attended = [];

    /** @var array<string, list<string>> */
    public array $selections = [];

    /** @var array<string, list<array{sku: string, product: string, state: string}>> */
    public array $orders = [];

    /** @var array<string, string> member => tier */
    public array $supporters = [];

    /** @var array<string, list<string>> */
    public array $tags = [];

    /** @var list<array<array-key, mixed>> */
    public array $asked = [];

    public function holdsTicket(string $member, string $event, array $ticketTypes): bool
    {
        $this->asked[] = ['ticket', $member, $event, $ticketTypes];
        $held = $this->tickets[$member][$event] ?? null;

        return null !== $held && ([] === $ticketTypes || [] !== array_intersect($held, $ticketTypes));
    }

    public function attended(string $member, string $occurrence): bool
    {
        $this->asked[] = ['date', $member, $occurrence];

        return \in_array($occurrence, $this->attended[$member] ?? [], true);
    }

    public function inSelection(string $member, string $selection): bool
    {
        $this->asked[] = ['selection', $member, $selection];

        return \in_array($selection, $this->selections[$member] ?? [], true);
    }

    public function bought(string $member, PurchaseFilter $filter): bool
    {
        $this->asked[] = ['purchase', $member, $filter];

        foreach ($this->orders[$member] ?? [] as $order) {
            if (([] === $filter->skus || \in_array($order['sku'], $filter->skus, true))
                && ([] === $filter->products || \in_array($order['product'], $filter->products, true))
                && ([] === $filter->orderStates || \in_array($order['state'], $filter->orderStates, true))) {
                return true;
            }
        }

        return false;
    }

    public function isActive(string $member, array $tiers): bool
    {
        $this->asked[] = ['supporter', $member, $tiers];
        $tier = $this->supporters[$member] ?? null;

        return null !== $tier && ([] === $tiers || \in_array($tier, $tiers, true));
    }

    public function hasAnyTag(string $member, array $tags): bool
    {
        $this->asked[] = ['tags', $member, $tags];

        return [] !== array_intersect($this->tags[$member] ?? [], $tags);
    }
}
