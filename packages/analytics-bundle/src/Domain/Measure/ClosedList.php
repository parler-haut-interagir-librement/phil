<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The values a site declares for one measure, and nothing else: what is not listed counts nothing, so a forged value
 * creates no counter and no value can name a person.
 */
final readonly class ClosedList
{
    public const int MAX_VALUES = 256;

    private const array DOWNLOADS = [
        '7z', 'apk', 'avi', 'csv', 'deb', 'dmg', 'doc', 'docx', 'epub', 'exe', 'gz', 'iso', 'mobi', 'mov', 'mp3', 'mp4',
        'msi', 'odp', 'ods', 'odt', 'ogg', 'pdf', 'ppt', 'pptx', 'rar', 'rpm', 'rtf', 'tar', 'txt', 'wav', 'webm', 'xls',
        'xlsx', 'zip',
    ];

    /**
     * @param array<string, string> $values each value under its own key
     */
    private function __construct(
        private array $values,
    ) {
    }

    /**
     * @param iterable<string> $values
     */
    public static function of(iterable $values): self
    {
        $set = [];

        foreach ($values as $value) {
            if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $value)) {
                throw InvalidValue::because(\sprintf('"%s" cannot be declared: 1 to 64 characters among a-z, 0-9, "_", "." and "-", starting with a letter or a digit.', $value));
            }

            $set[$value] = $value;
        }

        if (\count($set) > self::MAX_VALUES) {
            throw InvalidValue::because(\sprintf('A closed list holds at most %d values; this one holds %d.', self::MAX_VALUES, \count($set)));
        }

        return new self($set);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * The file extensions counted as downloads unless the site declares its own.
     */
    public static function downloads(): self
    {
        return self::of(self::DOWNLOADS);
    }

    public function has(string $value): bool
    {
        return isset($this->values[$value]);
    }

    /**
     * @return list<string> the values, for the page to hand to the collection script
     */
    public function toArray(): array
    {
        return array_values($this->values);
    }
}
