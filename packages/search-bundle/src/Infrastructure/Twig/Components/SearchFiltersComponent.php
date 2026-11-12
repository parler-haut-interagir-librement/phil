<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Twig\Components;

use LogicException;
use Phil\SearchBundle\Application\SearchRequest;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchBundle\Infrastructure\Twig\KitComponents;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The filters of a results page, `<twig:PhilSearchFilters :request="request" :result="result" :labels="labels" />`:
 * a form of checkboxes, one per value of each facet with its count, the values chosen as links removing them one at a
 * time or all together, and the count of results in a live region. It works as plain links and a plain `GET` form;
 * the `filters` controller submits it as a box is ticked and redraws the page in place.
 */
#[AsTwigComponent(name: 'PhilSearchFilters', template: '@PhilSearch/components/SearchFilters.html.twig', exposePublicProps: false)]
final class SearchFiltersComponent
{
    public ?SearchRequest $request = null;

    public ?SearchResult $result = null;

    /**
     * Each facet shown, in this order, and its name for a visitor.
     *
     * @var array<string, string>
     */
    public array $labels = [];

    /**
     * The name of a value for a visitor, per facet, when it is not the value itself: a category's title for its key.
     *
     * @var array<string, array<string, string>>
     */
    public array $valueLabels = [];

    /** The identifier of the component's root, kept from one page to the next. */
    public string $id = 'phil-search-filters';

    /** The identifier of the element holding the results, redrawn with the filters. */
    public ?string $results = null;

    /** Where the form goes: the same page when empty. */
    public string $action = '';

    public function __construct(
        private readonly KitComponents $kit,
    ) {
    }

    /**
     * The words looked for, kept in the form so that ticking a box keeps them.
     */
    public function text(): string
    {
        return $this->request()->text;
    }

    /**
     * How many documents the search found.
     */
    public function total(): int
    {
        return $this->result()->total;
    }

    /**
     * The name under which the application installed a recipe of the kit.
     */
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    /**
     * Each facet shown with its values: those the search counted, and the values chosen that it found none of, so that
     * they can still be unticked.
     *
     * @return list<array{field: string, label: string, values: list<array{value: string, label: string, count: int, checked: bool, id: string}>}>
     */
    public function facets(): array
    {
        $facets = [];

        foreach ($this->labels as $field => $label) {
            $values = [];
            $seen = [];

            foreach ($this->result()->facet($field) as $facetValue) {
                $values[] = $this->value($field, $facetValue->value, $facetValue->count);
                $seen[] = $facetValue->value;
            }

            foreach ($this->request()->selection[$field] ?? [] as $chosen) {
                if (!\in_array($chosen, $seen, true)) {
                    $values[] = $this->value($field, $chosen, 0);
                }
            }

            if ([] !== $values) {
                $facets[] = ['field' => $field, 'label' => $label, 'values' => $values];
            }
        }

        return $facets;
    }

    /**
     * The values chosen, each with the link that removes it.
     *
     * @return list<array{facet: string, label: string, value: string, href: string}>
     */
    public function chosen(): array
    {
        $chosen = [];

        foreach ($this->request()->selected() as ['facet' => $facet, 'value' => $value]) {
            $chosen[] = [
                'facet' => $this->labels[$facet] ?? $facet,
                'label' => $this->valueLabels[$facet][$value] ?? $value,
                'value' => $value,
                'href' => $this->request()->without($facet, $value)->queryString(),
            ];
        }

        return $chosen;
    }

    public function clearHref(): string
    {
        return $this->request()->withoutFilters()->queryString();
    }

    private function request(): SearchRequest
    {
        return $this->request ?? throw new LogicException('PhilSearchFilters is given the request of the page: :request="request".');
    }

    private function result(): SearchResult
    {
        return $this->result ?? throw new LogicException('PhilSearchFilters is given the result of the search: :result="result".');
    }

    /**
     * @return array{value: string, label: string, count: int, checked: bool, id: string}
     */
    private function value(string $field, string $value, int $count): array
    {
        return [
            'value' => $value,
            'label' => $this->valueLabels[$field][$value] ?? $value,
            'count' => $count,
            'checked' => $this->request()->isSelected($field, $value),
            // The same box keeps the same identifier from one page to the next, so that the focus comes back to it.
            'id' => \sprintf('%s-%s-%s', $this->id, $field, hash('xxh3', $value)),
        ];
    }
}
