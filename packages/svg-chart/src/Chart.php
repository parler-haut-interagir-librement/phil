<?php

declare(strict_types=1);

namespace Phil\SvgChart;

use Phil\SvgChart\Exception\InvalidChart;

/**
 * What a chart shows: its kind, its labels, its series and its title, checked against limits so that a hostile
 * configuration costs no more than a large honest one.
 *
 * `fromConfig()` reads the shape of a Chart.js configuration — `type`, `data.labels`, `data.datasets[].label`,
 * `.data`, `.backgroundColor`, `.borderColor` and `options.plugins.title.text` — and ignores the other options, which
 * set how a browser animates or styles the chart.
 */
final readonly class Chart
{
    public const int MAX_LABELS = 500;

    public const int MAX_DATASETS = 24;

    public const int MAX_TEXT = 200;

    private const string COLOUR = '/^#[0-9a-fA-F]{6}$/D';

    /**
     * @param list<string>  $labels
     * @param list<Dataset> $datasets
     *
     * @throws InvalidChart when there are more labels or series than the limits, a series has not one value per
     *                      label, a text is longer than 200 characters, or a colour is not written `#rrggbb`
     */
    public function __construct(
        public ChartType $type,
        public array $labels,
        public array $datasets,
        public string $title = '',
    ) {
        if (\count($labels) > self::MAX_LABELS) {
            throw InvalidChart::at('data.labels', \sprintf('more than %d labels', self::MAX_LABELS));
        }

        if (\count($datasets) > self::MAX_DATASETS) {
            throw InvalidChart::at('data.datasets', \sprintf('more than %d series', self::MAX_DATASETS));
        }

        foreach ([...$labels, $title] as $text) {
            if (mb_strlen($text) > self::MAX_TEXT) {
                throw InvalidChart::at('data.labels', \sprintf('a text is longer than %d characters', self::MAX_TEXT));
            }
        }

        foreach ($datasets as $i => $dataset) {
            if (\count($dataset->values) !== \count($labels)) {
                throw InvalidChart::at('data.datasets.'.$i.'.data', \sprintf('%d values for %d labels', \count($dataset->values), \count($labels)));
            }

            if (mb_strlen($dataset->label) > self::MAX_TEXT) {
                throw InvalidChart::at('data.datasets.'.$i.'.label', \sprintf('longer than %d characters', self::MAX_TEXT));
            }

            if (null !== $dataset->colour) {
                self::colour($dataset->colour, 'data.datasets.'.$i.'.colour');
            }

            foreach ($dataset->colours as $j => $colour) {
                self::colour($colour, 'data.datasets.'.$i.'.colours.'.$j);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidChart
     */
    public static function fromConfig(array $config): self
    {
        $type = \is_string($config['type'] ?? null) ? ChartType::tryFrom($config['type']) : null;

        if (null === $type) {
            throw InvalidChart::at('type', 'one of bar, line, pie and doughnut is expected');
        }

        $data = self::object($config['data'] ?? null, 'data');
        $labels = [];

        foreach (self::list($data['labels'] ?? [], 'data.labels', self::MAX_LABELS) as $i => $label) {
            $labels[] = self::text($label, 'data.labels.'.$i);
        }

        $datasets = [];

        foreach (self::list($data['datasets'] ?? [], 'data.datasets', self::MAX_DATASETS) as $i => $dataset) {
            $datasets[] = self::dataset(self::object($dataset, 'data.datasets.'.$i), 'data.datasets.'.$i);
        }

        $options = \is_array($config['options'] ?? null) ? $config['options'] : [];
        $plugins = \is_array($options['plugins'] ?? null) ? $options['plugins'] : [];
        $title = \is_array($plugins['title'] ?? null) ? $plugins['title'] : [];

        return new self($type, $labels, $datasets, \is_string($title['text'] ?? null) ? $title['text'] : '');
    }

    /**
     * @param array<array-key, mixed> $dataset
     */
    private static function dataset(array $dataset, string $path): Dataset
    {
        $values = [];

        foreach (self::list($dataset['data'] ?? [], $path.'.data', self::MAX_LABELS) as $i => $value) {
            if (null !== $value && !\is_int($value) && (!\is_float($value) || !is_finite($value))) {
                throw InvalidChart::at($path.'.data.'.$i, 'a number or null is expected');
            }

            $values[] = null === $value ? null : (float) $value;
        }

        $background = $dataset['backgroundColor'] ?? null;
        $colours = [];

        if (\is_array($background)) {
            foreach (self::list($background, $path.'.backgroundColor', self::MAX_LABELS) as $i => $colour) {
                $colours[] = self::colour($colour, $path.'.backgroundColor.'.$i);
            }

            $background = null;
        }

        $colour = $dataset['borderColor'] ?? $background;

        return new Dataset(
            null === ($dataset['label'] ?? null) ? '' : self::text($dataset['label'], $path.'.label'),
            $values,
            null === $colour ? null : self::colour($colour, $path.'.'.(isset($dataset['borderColor']) ? 'borderColor' : 'backgroundColor')),
            $colours,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function object(mixed $value, string $path): array
    {
        if (!\is_array($value) || array_is_list($value) && [] !== $value) {
            throw InvalidChart::at($path, 'an object is expected');
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value, string $path, int $limit): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidChart::at($path, 'a list is expected');
        }

        if (\count($value) > $limit) {
            throw InvalidChart::at($path, \sprintf('more than %d items', $limit));
        }

        return $value;
    }

    private static function text(mixed $value, string $path): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        if (!\is_string($value)) {
            throw InvalidChart::at($path, 'a text is expected');
        }

        return $value;
    }

    private static function colour(mixed $value, string $path): string
    {
        if (!\is_string($value) || 1 !== preg_match(self::COLOUR, $value)) {
            throw InvalidChart::at($path, 'a colour written #rrggbb is expected');
        }

        return strtolower($value);
    }
}
