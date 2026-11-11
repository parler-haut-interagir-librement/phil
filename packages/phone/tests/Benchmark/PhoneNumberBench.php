<?php

declare(strict_types=1);

namespace Phil\Phone\Tests\Benchmark;

use Generator;
use Phil\Phone\Metadata\Territories;
use Phil\Phone\PhoneNumber;

/**
 * Every form field, validator and Twig `|phone` call parses a number and asks whether it is valid; for the +1 country
 * code that walks up to 25 territory plans with regexes, so the later a territory comes in the list, the more it costs.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"phone"})
 */
final class PhoneNumberBench
{
    /**
     * @var array<string, PhoneNumber>
     */
    private array $parsed = [];

    public function __construct()
    {
        foreach ($this->numbers() as $number) {
            $this->parsed[$number['input']] = PhoneNumber::parse($number['input'], $number['region']);
        }
    }

    /**
     * Loads every plan the number needs first, so the measure is the walk and not the first `require` of the data.
     *
     * @param array{input: string, region: ?string} $params
     */
    public function setUp(array $params): void
    {
        $number = PhoneNumber::parse($params['input'], $params['region']);

        foreach (Territories::regionsOf($number->countryCode) as $region) {
            Territories::of($region, $number->countryCode);
        }
    }

    /**
     * @ParamProviders("numbers")
     *
     * @param array{input: string, region: ?string} $params
     */
    public function benchParse(array $params): void
    {
        PhoneNumber::parse($params['input'], $params['region']);
    }

    /**
     * What a validator does: parse, then check the number against its country's plan.
     *
     * @ParamProviders("numbers")
     *
     * @param array{input: string, region: ?string} $params
     */
    public function benchParseAndIsValid(array $params): void
    {
        PhoneNumber::parse($params['input'], $params['region'])->isValid();
    }

    /**
     * `region()` and `type()` on a number not asked yet, a copy of the parsed one: the first walks the plans, the
     * second reads what the number kept.
     *
     * @ParamProviders("numbers")
     *
     * @param array{input: string, region: ?string} $params
     */
    public function benchRegionAndType(array $params): void
    {
        $number = clone $this->parsed[$params['input']];
        $number->region();
        $number->type();
    }

    /**
     * @return Generator<string, array{input: string, region: ?string}>
     */
    public function numbers(): Generator
    {
        yield 'US +1 212 (1st plan)' => ['input' => '+1 212-555-0123', 'region' => null];
        yield 'CA +1 514 (8th plan)' => ['input' => '+1 514-555-0123', 'region' => null];
        yield 'JM +1 876 (13th plan)' => ['input' => '+1 876-927-1234', 'region' => null];
        yield 'VI +1 340 (25th plan)' => ['input' => '+1 340-642-1234', 'region' => null];
        yield 'FR national with region' => ['input' => '06 12 34 56 78', 'region' => 'FR'];
        yield 'GB +44 with extension' => ['input' => '+44 20 7946 0958 ext. 12', 'region' => null];
    }
}
