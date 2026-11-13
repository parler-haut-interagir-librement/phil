<?php

declare(strict_types=1);

namespace Phil\Citations;

use JsonException;
use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Internal\DateText;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;

/**
 * References read from and written to CSL-JSON, the exchange format of citation managers: a list of items, each with
 * an `id`, a `type`, its names as objects, its dates as `date-parts`, and its other variables as text or numbers.
 *
 * Reading is bounded in size, items and depth. A name given by `literal`, a date given by `raw` (`2020`, `2020-05`,
 * `2020-05-01`, a range with `/`) or `literal`, and numbers in place of text are accepted; a variable that is neither
 * text nor a number, and the keys CSL-JSON does not define for names and dates, are left out.
 */
final readonly class CslJson
{
    private const array NAME_PARTS = [
        'family' => 'family',
        'given' => 'given',
        'dropping-particle' => 'droppingParticle',
        'non-dropping-particle' => 'nonDroppingParticle',
        'suffix' => 'suffix',
        'literal' => 'literal',
    ];

    /**
     * @param int $maxBytes the longest JSON read
     * @param int $maxItems the most items read
     */
    public function __construct(
        private int $maxBytes = 5_000_000,
        private int $maxItems = 10_000,
    ) {
    }

    /**
     * @return list<Reference>
     *
     * @throws InvalidReference JSON that is not a list of valid items, or beyond the limits
     */
    public function decode(string $json): array
    {
        if (\strlen($json) > $this->maxBytes) {
            throw InvalidReference::because(\sprintf('the CSL-JSON is longer than %d bytes', $this->maxBytes));
        }

        try {
            $items = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidReference::because('the CSL-JSON is not JSON: '.$jsonException->getMessage());
        }

        if (\is_array($items) && !array_is_list($items)) {
            $items = [$items];
        }

        if (!\is_array($items)) {
            throw InvalidReference::because('the CSL-JSON is not a list of items');
        }

        if (\count($items) > $this->maxItems) {
            throw InvalidReference::because(\sprintf('the CSL-JSON holds more than %d items', $this->maxItems));
        }

        return array_values(array_map($this->item(...), $items));
    }

    /**
     * @param iterable<Reference> $references
     */
    public function encode(iterable $references): string
    {
        $items = [];

        foreach ($references as $reference) {
            $item = ['id' => $reference->id, 'type' => $reference->type->value];

            foreach ($reference->allNames() as $role => $names) {
                $item[$role] = array_map($this->nameToArray(...), $names);
            }

            foreach ($reference->allDates() as $variable => $date) {
                $item[$variable] = $this->dateToArray($date);
            }

            $items[] = $item + $reference->allFields();
        }

        return json_encode($items, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    private function item(mixed $item): Reference
    {
        if (!\is_array($item)) {
            throw InvalidReference::because('an item of the CSL-JSON is not an object');
        }

        $id = $item['id'] ?? null;
        $type = \is_string($item['type'] ?? null) ? ItemType::tryFrom($item['type']) : null;

        if (!\is_string($id) && !\is_int($id)) {
            throw InvalidReference::because('an item has no id');
        }

        if (!$type instanceof ItemType) {
            throw InvalidReference::because(\sprintf('the item "%s" has no type of CSL 1.0.2', $id));
        }

        $names = [];
        $dates = [];
        $fields = [];

        foreach ($item as $key => $value) {
            $key = (string) $key;

            if (null !== NameVariable::tryFrom($key)) {
                $names[$key] = \is_array($value) ? array_map($this->name(...), array_values($value)) : [];
            } elseif (null !== DateVariable::tryFrom($key)) {
                $dates[$key] = $this->date($value);
            } elseif (\is_string($value) || \is_int($value) || \is_float($value)) {
                $fields[$key] = (string) $value;
            }
        }

        unset($fields['id'], $fields['type']);

        return new Reference((string) $id, $type, $names, $dates, $fields);
    }

    private function name(mixed $name): Name
    {
        if (\is_string($name)) {
            return Name::literal($name);
        }

        if (!\is_array($name)) {
            throw InvalidReference::because('a name is not an object');
        }

        $parts = [];

        foreach (self::NAME_PARTS as $key => $parameter) {
            $part = $name[$key] ?? null;

            if (\is_string($part) && '' !== trim($part)) {
                $parts[$parameter] = trim($part);
            }
        }

        return new Name(...$parts);
    }

    private function date(mixed $date): CslDate
    {
        if (!\is_array($date)) {
            throw InvalidReference::because('a date is not an object');
        }

        $circa = \in_array($date['circa'] ?? false, [true, 1, '1', 'true'], true);
        $literal = \is_string($date['literal'] ?? null) ? $date['literal'] : null;
        $ranges = $date['date-parts'] ?? null;

        if (\is_array($ranges) && [] !== $ranges) {
            $season = $this->number($date['season'] ?? null);
            $start = $this->parts(array_first($ranges), $season);
            $end = isset($ranges[1]) ? $this->parts($ranges[1], null) : null;

            return new CslDate($start, $end, $circa, $literal);
        }

        if (\is_string($date['raw'] ?? null)) {
            return DateText::parse($date['raw'], $circa);
        }

        return new CslDate(null, null, $circa, $literal);
    }

    private function parts(mixed $parts, ?int $season): PartialDate
    {
        $year = \is_array($parts) ? $this->number($parts[0] ?? null) : null;

        if (null === $year) {
            throw InvalidReference::because('the date-parts of a date do not start with a year');
        }

        $month = $this->number($parts[1] ?? null);
        $day = $this->number($parts[2] ?? null);

        if (null !== $month && $month > 12) {
            [$season, $month, $day] = [$month - 12, null, null];
        }

        return new PartialDate($year, $month, $day, $season);
    }

    /**
     * A part of a date, as a number or as text of at most five digits; null for anything else.
     */
    private function number(mixed $value): ?int
    {
        if (\is_int($value) && abs($value) <= 99_999) {
            return $value;
        }

        return \is_string($value) && 1 === preg_match('/^-?\d{1,5}$/D', $value) ? (int) $value : null;
    }

    /**
     * @return array<string, string>
     */
    private function nameToArray(Name $name): array
    {
        $array = [];

        foreach (self::NAME_PARTS as $key => $property) {
            if (null !== $name->{$property}) {
                $array[$key] = $name->{$property};
            }
        }

        return $array;
    }

    /**
     * @return array<string, mixed>
     */
    private function dateToArray(CslDate $date): array
    {
        $array = [];

        if ($date->start instanceof PartialDate) {
            $array['date-parts'] = $date->end instanceof PartialDate ? [$date->start->parts(), $date->end->parts()] : [$date->start->parts()];

            if (null !== $date->start->season) {
                $array['season'] = $date->start->season;
            }
        }

        if ($date->circa) {
            $array['circa'] = true;
        }

        if (null !== $date->literal) {
            $array['literal'] = $date->literal;
        }

        return $array;
    }
}
