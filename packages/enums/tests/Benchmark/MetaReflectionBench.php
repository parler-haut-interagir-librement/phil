<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Benchmark;

use Generator;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;
use Phil\Enums\Tests\Benchmark\Fixture\Region;
use Phil\Enums\Tests\Fixture\MetaEnum;
use Phil\Enums\Tests\Fixture\Planet;
use ReflectionAttribute;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * Every accessor, form option list and Twig label reads case metadata through Reflection, which keeps what it read
 * per process; the uncached subject reads the attributes as a first call would, to show what the cache saves.
 */
final class MetaReflectionBench
{
    /**
     * @var array<string, list<UnitEnum>>
     */
    private array $cases;

    /**
     * @var array<string, CaseCollection<UnitEnum>>
     */
    private array $collections = [];

    public function __construct()
    {
        $this->cases = ['MetaEnum' => MetaEnum::cases(), 'Planet' => Planet::cases(), 'Region' => Region::cases()];

        foreach ($this->cases as $enum => $cases) {
            $this->collections[$enum] = CaseCollection::of($cases);

            // Warm the per-process cache, as every request after the first finds it.
            foreach ($cases as $case) {
                Reflection::metaValue(Label::class, $case);
            }
        }
    }

    /**
     * @ParamProviders("enums")
     *
     * @param array{enum: string} $params
     */
    public function benchMetaValueOfEveryCase(array $params): void
    {
        foreach ($this->cases[$params['enum']] as $case) {
            Reflection::metaValue(Label::class, $case);
        }
    }

    /**
     * @ParamProviders("enums")
     *
     * @param array{enum: string} $params
     */
    public function benchUncachedReadOfEveryCase(array $params): void
    {
        foreach ($this->cases[$params['enum']] as $case) {
            foreach (new ReflectionEnumUnitCase($case::class, $case->name)->getAttributes(AbstractMetaProperty::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $attribute->newInstance();
            }
        }
    }

    /**
     * @ParamProviders("enums")
     *
     * @param array{enum: string} $params
     */
    public function benchWhereMeta(array $params): void
    {
        $this->collections[$params['enum']]->whereMeta(Label::class, 'Boiling');
    }

    /**
     * @ParamProviders("enums")
     *
     * @param array{enum: string} $params
     */
    public function benchGroupByMeta(array $params): void
    {
        $this->collections[$params['enum']]->groupByMeta(Group::class);
    }

    /**
     * @return Generator<string, array{enum: string}>
     */
    public function enums(): Generator
    {
        yield '3 cases' => ['enum' => 'MetaEnum'];
        yield '5 cases' => ['enum' => 'Planet'];
        yield '60 cases' => ['enum' => 'Region'];
    }
}
