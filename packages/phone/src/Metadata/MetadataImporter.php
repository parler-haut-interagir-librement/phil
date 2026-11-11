<?php

declare(strict_types=1);

namespace Phil\Phone\Metadata;

use DOMDocument;
use DOMElement;
use Phil\Phone\Exception\InvalidMetadata;

/**
 * Turns libphonenumber's `PhoneNumberMetadata.xml` into the library's data: one PHP file per territory, and the
 * territories of each country code, main one first.
 *
 * Patterns lose the whitespace the XML lays them out with; formats get their territory's national prefix rule,
 * `$NP` and `$FG` already replaced; a description's possible lengths are expanded, and the general description's are
 * the union of the others'.
 */
final class MetadataImporter
{
    private const array TYPES = ['fixedLine', 'mobile', 'tollFree', 'premiumRate', 'sharedCost', 'voip', 'personalNumber', 'pager', 'uan', 'voicemail'];

    /**
     * Replaces the data in `$directory` with the plans of `$xml`.
     *
     * Every territory is read before anything is written, so metadata that is not libphonenumber's leaves the data in
     * place. Each file is written beside its final name and renamed over it, and only then are the plans the new
     * metadata no longer has removed: a failure leaves every file whole, the old plan or the new one.
     *
     * @return int the number of territories written
     */
    public function import(string $xml, string $directory): int
    {
        $document = new DOMDocument();

        if (!@$document->loadXML($xml)) {
            throw InvalidMetadata::notXml();
        }

        $codes = [];
        $files = [];

        foreach ($document->getElementsByTagName('territory') as $element) {
            $territory = $this->territory($element);
            $code = $territory['countryCode'];
            $region = $territory['id'];

            if ('001' === $region) {
                $codes[$code] = ['001'];
                $file = \sprintf('001-%d', $code);
            } else {
                $codes[$code] ??= [];
                $territory['mainCountryForCode'] ? array_unshift($codes[$code], $region) : $codes[$code][] = $region;
                $file = $region;
            }

            unset($territory['mainCountryForCode']);
            $files[$file.'.php'] = $territory;
        }

        $count = \count($files);
        ksort($codes);
        $files['country-codes.php'] = $codes;

        foreach ($files as $name => $data) {
            $this->write($directory, $name, $data);
        }

        $previous = glob($directory.'/*.php');

        foreach (false === $previous ? [] : $previous as $stale) {
            if (!isset($files[basename($stale)])) {
                unlink($stale);
            }
        }

        return $count;
    }

    /**
     * @return array{id: string, countryCode: int, mainCountryForCode: bool, internationalPrefix: ?string, preferredInternationalPrefix: ?string, nationalPrefix: ?string, nationalPrefixForParsing: ?string, nationalPrefixTransformRule: ?string, leadingDigits: ?string, preferredExtnPrefix: ?string, general: array{pattern: string, lengths: list<int>}, types: array<string, array{pattern: string, lengths: list<int>}>, formats: list<array{pattern: string, leadingDigits: ?string, format: string, nationalRule: ?string, intlFormat: ?string}>}
     */
    private function territory(DOMElement $element): array
    {
        $id = $element->getAttribute('id');

        if ('' === $id || !ctype_digit($element->getAttribute('countryCode'))) {
            throw InvalidMetadata::territory($id);
        }

        $nationalPrefix = $this->patternAttribute($element, 'nationalPrefix');
        $types = [];
        $lengths = [];

        foreach (self::TYPES as $type) {
            $description = $this->child($element, $type);

            if (!$description instanceof DOMElement) {
                continue;
            }

            $possible = $this->lengths($description);

            if ([] === $possible) {
                continue;
            }

            $types[$type] = ['pattern' => $this->pattern($description, 'nationalNumberPattern'), 'lengths' => $possible];
            $lengths = [...$lengths, ...$possible];
        }

        $lengths = array_unique($lengths);
        sort($lengths);
        $general = $this->child($element, 'generalDesc');

        return [
            'id' => $id,
            'countryCode' => (int) $element->getAttribute('countryCode'),
            'mainCountryForCode' => 'true' === $element->getAttribute('mainCountryForCode'),
            'internationalPrefix' => $this->patternAttribute($element, 'internationalPrefix'),
            'preferredInternationalPrefix' => $this->patternAttribute($element, 'preferredInternationalPrefix'),
            'nationalPrefix' => $nationalPrefix,
            'nationalPrefixForParsing' => $this->patternAttribute($element, 'nationalPrefixForParsing') ?? $nationalPrefix,
            'nationalPrefixTransformRule' => $this->patternAttribute($element, 'nationalPrefixTransformRule'),
            'leadingDigits' => $this->patternAttribute($element, 'leadingDigits'),
            'preferredExtnPrefix' => $element->hasAttribute('preferredExtnPrefix') ? $element->getAttribute('preferredExtnPrefix') : null,
            'general' => ['pattern' => $general instanceof DOMElement ? $this->pattern($general, 'nationalNumberPattern') : '\d+', 'lengths' => $lengths],
            'types' => $types,
            'formats' => $this->formats($element, $nationalPrefix),
        ];
    }

    /**
     * @return list<array{pattern: string, leadingDigits: ?string, format: string, nationalRule: ?string, intlFormat: ?string}>
     */
    private function formats(DOMElement $territory, ?string $nationalPrefix): array
    {
        $available = $this->child($territory, 'availableFormats');

        if (!$available instanceof DOMElement) {
            return [];
        }

        $defaultRule = $this->attribute($territory, 'nationalPrefixFormattingRule');
        $formats = [];

        foreach ($available->getElementsByTagName('numberFormat') as $format) {
            $leading = null;

            foreach ($format->getElementsByTagName('leadingDigits') as $digits) {
                $leading = $this->strip($digits->textContent);
            }

            $national = trim($this->text($format, 'format'));
            $intl = $this->child($format, 'intlFormat');
            $rule = $this->attribute($format, 'nationalPrefixFormattingRule') ?? $defaultRule;

            $formats[] = [
                'pattern' => $this->strip($format->getAttribute('pattern')),
                'leadingDigits' => $leading,
                'format' => $national,
                'nationalRule' => null === $rule ? null : str_replace(['$NP', '$FG'], [$nationalPrefix ?? '', '$1'], $rule),
                'intlFormat' => match (true) {
                    !$intl instanceof DOMElement => $national,
                    'NA' === trim($intl->textContent) => null,
                    default => trim($intl->textContent),
                },
            ];
        }

        return $formats;
    }

    /**
     * @return list<int>
     */
    private function lengths(DOMElement $description): array
    {
        $possible = $this->child($description, 'possibleLengths');
        $lengths = [];

        if (!$possible instanceof DOMElement) {
            return [];
        }

        foreach (explode(',', $possible->getAttribute('national')) as $part) {
            if (1 === preg_match('/^\[(?<from>\d+)-(?<to>\d+)\]$/', $part, $range)) {
                $lengths = [...$lengths, ...range((int) $range['from'], (int) $range['to'])];
            } elseif (ctype_digit($part)) {
                $lengths[] = (int) $part;
            }
        }

        return $lengths;
    }

    private function pattern(DOMElement $parent, string $name): string
    {
        return $this->strip($this->text($parent, $name));
    }

    private function text(DOMElement $parent, string $name): string
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $name === $node->tagName) {
                return $node->textContent;
            }
        }

        return '';
    }

    private function child(DOMElement $parent, string $name): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $name === $node->tagName) {
                return $node;
            }
        }

        return null;
    }

    /**
     * A rule keeps its inner spaces: `$NP $FG`.
     */
    private function attribute(DOMElement $element, string $name): ?string
    {
        $value = trim($element->getAttribute($name));

        return '' === $value ? null : $value;
    }

    private function patternAttribute(DOMElement $element, string $name): ?string
    {
        $value = $this->strip($element->getAttribute($name));

        return '' === $value ? null : $value;
    }

    /**
     * Patterns are laid out over lines in the XML, which attributes turn into spaces: no space is part of one.
     */
    private function strip(string $value): string
    {
        return preg_replace('/\s+/', '', $value) ?? '';
    }

    /**
     * Writes a file beside its final name, then renames it over that name in one step.
     */
    private function write(string $directory, string $name, mixed $data): void
    {
        $file = $directory.'/'.$name;
        $written = $file.'.new';
        // Written as the repository's code style writes a PHP file, so that its tools leave the data as it is.
        $php = "<?php\n\ndeclare(strict_types=1);\n\n// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\\Phone\\Metadata\\MetadataImporter.\nreturn ".$this->export($data).";\n";

        if (\strlen($php) !== @file_put_contents($written, $php) || !@rename($written, $file)) {
            if (is_file($written)) {
                unlink($written);
            }

            throw InvalidMetadata::unwritable($file);
        }
    }

    private function export(mixed $value): string
    {
        return match (true) {
            \is_array($value) => '['.implode(', ', array_map(
                fn (int|string $key): string => (array_is_list($value) ? '' : $this->export($key).' => ').$this->export($value[$key]),
                array_keys($value),
            )).']',
            \is_string($value) => "'".addcslashes($value, "'\\")."'",
            \is_int($value) => (string) $value,
            default => 'null',
        };
    }
}
