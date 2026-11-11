<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Phil\ContentWarnings\Exception\InvalidWarning;
use Phil\ContentWarnings\Exception\UnreadableWarnings;
use Traversable;

/**
 * The warnings of a piece of content — a game, a book, an article, an event — one per theme, in the order given, 100
 * at most. Immutable; kept as an array for one JSON column with `toArray()` and `fromArray()`.
 *
 * @implements IteratorAggregate<int, ContentWarning>
 */
final readonly class ContentWarnings implements Countable, IteratorAggregate
{
    public const int MAX_WARNINGS = 100;

    /**
     * @var array<string, ContentWarning>
     */
    private array $warnings;

    /**
     * @throws InvalidWarning for a theme given twice or more than 100 warnings
     */
    private function __construct(ContentWarning ...$warnings)
    {
        if (\count($warnings) > self::MAX_WARNINGS) {
            throw InvalidWarning::because(\sprintf('a piece of content has at most %d warnings', self::MAX_WARNINGS));
        }

        $byTheme = [];

        foreach ($warnings as $warning) {
            if (isset($byTheme[$warning->theme])) {
                throw InvalidWarning::because(\sprintf('the theme "%s" is given twice', $warning->theme));
            }

            $byTheme[$warning->theme] = $warning;
        }

        $this->warnings = $byTheme;
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @throws InvalidWarning
     */
    public static function of(ContentWarning ...$warnings): self
    {
        return new self(...$warnings);
    }

    /**
     * A warning for each theme key, without severity nor note.
     *
     * @throws InvalidWarning
     */
    public static function forThemes(string ...$themes): self
    {
        return new self(...array_map(static fn (string $theme): ContentWarning => new ContentWarning($theme), $themes));
    }

    /**
     * Reads back what `toArray()` wrote.
     *
     * @param array<array-key, mixed> $stored
     *
     * @throws UnreadableWarnings for anything `toArray()` does not write
     */
    public static function fromArray(array $stored): self
    {
        $warnings = [];

        foreach ($stored as $entry) {
            if (!\is_array($entry) || [] !== array_diff(array_keys($entry), ['theme', 'severity', 'note']) || !\is_string($entry['theme'] ?? null)) {
                throw UnreadableWarnings::because('each warning is an array with a "theme" and an optional "severity" and "note"');
            }

            $severity = $entry['severity'] ?? null;
            $note = $entry['note'] ?? null;

            if ((null !== $severity && !\is_string($severity)) || (null !== $note && !\is_string($note))) {
                throw UnreadableWarnings::because(\sprintf('the severity and the note of "%s" are strings', $entry['theme']));
            }

            $level = null === $severity ? null : Severity::tryFrom($severity);

            if (null !== $severity && null === $level) {
                throw UnreadableWarnings::because(\sprintf('"%s" is not a severity', $severity));
            }

            try {
                $warnings[] = new ContentWarning($entry['theme'], $level, $note);
            } catch (InvalidWarning $exception) {
                throw UnreadableWarnings::because($exception->getMessage());
            }
        }

        try {
            return new self(...$warnings);
        } catch (InvalidWarning $invalidWarning) {
            throw UnreadableWarnings::because($invalidWarning->getMessage());
        }
    }

    /**
     * @return list<array{theme: string, severity?: string, note?: string}>
     */
    public function toArray(): array
    {
        $stored = [];

        foreach ($this->warnings as $warning) {
            $entry = ['theme' => $warning->theme];

            if ($warning->severity instanceof Severity) {
                $entry['severity'] = $warning->severity->value;
            }

            if (null !== $warning->note) {
                $entry['note'] = $warning->note;
            }

            $stored[] = $entry;
        }

        return $stored;
    }

    public function get(string $theme): ?ContentWarning
    {
        return $this->warnings[$theme] ?? null;
    }

    public function has(string $theme): bool
    {
        return isset($this->warnings[$theme]);
    }

    /**
     * @return list<string>
     */
    public function themes(): array
    {
        return array_map(strval(...), array_keys($this->warnings));
    }

    /**
     * These warnings with this one added, or replacing the warning of its theme in its place.
     *
     * @throws InvalidWarning past 100 warnings
     */
    public function with(ContentWarning $warning): self
    {
        $warnings = $this->warnings;
        $warnings[$warning->theme] = $warning;

        return new self(...array_values($warnings));
    }

    public function without(string $theme): self
    {
        $warnings = $this->warnings;
        unset($warnings[$theme]);

        return new self(...array_values($warnings));
    }

    public function isEmpty(): bool
    {
        return [] === $this->warnings;
    }

    #[Override]
    public function count(): int
    {
        return \count($this->warnings);
    }

    /**
     * @return Traversable<int, ContentWarning>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->warnings));
    }
}
