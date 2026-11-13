<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMediaBundle\Application\Exception\InvalidTemplateParameters;

/**
 * The parameters of a printed template, read by path: each value checked for its kind, a missing or wrong one refused
 * with its path, so that a job that cannot be drawn fails once, plainly.
 *
 * @internal
 */
final readonly class Parameters
{
    private const array SHEETS = ['letter30', 'letter10', 'letter6', 'a4x21', 'a4x8'];

    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        private string $template,
        private array $values,
        private string $path = '',
    ) {
    }

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;

        return \is_string($value) ? $value : throw $this->invalid($key, 'a string');
    }

    public function optionalString(string $key): ?string
    {
        return null === ($this->values[$key] ?? null) ? null : $this->string($key);
    }

    public function int(string $key, int $default): int
    {
        $value = $this->values[$key] ?? $default;

        return \is_int($value) ? $value : throw $this->invalid($key, 'an integer');
    }

    public function float(string $key, float $default): float
    {
        $value = $this->values[$key] ?? $default;

        return \is_int($value) || \is_float($value) ? (float) $value : throw $this->invalid($key, 'a number');
    }

    public function bool(string $key, bool $default): bool
    {
        $value = $this->values[$key] ?? $default;

        return \is_bool($value) ? $value : throw $this->invalid($key, 'true or false');
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value) || [] !== array_filter($value, static fn (mixed $item): bool => !\is_string($item))) {
            throw $this->invalid($key, 'a list of strings');
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * Each entry of a list of objects, as parameters of its own.
     *
     * @return list<self>
     */
    public function list(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw $this->invalid($key, 'a list');
        }

        $entries = [];

        foreach ($value as $index => $entry) {
            if (!\is_array($entry)) {
                throw $this->invalid($key.'.'.$index, 'an object');
            }

            $entries[] = new self($this->template, $entry, $this->at($key.'.'.$index));
        }

        return $entries;
    }

    /**
     * An object, as parameters of its own; empty when it is missing.
     */
    public function object(string $key): self
    {
        $value = $this->values[$key] ?? [];

        return \is_array($value) ? new self($this->template, $value, $this->at($key)) : throw $this->invalid($key, 'an object');
    }

    /**
     * The keys of the object, as integers, such as the days of a month.
     *
     * @return list<int>
     */
    public function integerKeys(): array
    {
        $keys = [];

        foreach (array_keys($this->values) as $key) {
            $keys[] = \is_int($key) ? $key : throw $this->invalid($key, 'a day of the month as a key');
        }

        return $keys;
    }

    public function color(string $key): ?Color
    {
        $value = $this->optionalString($key);

        return null === $value ? null : Color::hex($value);
    }

    /**
     * The sheet named by `sheet`: one of the library's common stock sizes, or the default given.
     */
    public function sheet(LabelSheet $default): LabelSheet
    {
        $name = $this->optionalString('sheet');

        if (null === $name) {
            return $default;
        }

        return match ($name) {
            'letter30' => LabelSheet::letter30(),
            'letter10' => LabelSheet::letter10(),
            'letter6' => LabelSheet::letter6(),
            'a4x21' => LabelSheet::a4x21(),
            'a4x8' => LabelSheet::a4x8(),
            default => throw $this->invalid('sheet', 'one of '.implode(', ', self::SHEETS)),
        };
    }

    /**
     * The document's title, from `title`.
     */
    public function metadata(): Metadata
    {
        return new Metadata(title: $this->optionalString('title'));
    }

    private function at(string $key): string
    {
        return '' === $this->path ? $key : $this->path.'.'.$key;
    }

    private function invalid(string $key, string $expected): InvalidTemplateParameters
    {
        return InvalidTemplateParameters::at($this->template, $this->at($key), $expected);
    }
}
