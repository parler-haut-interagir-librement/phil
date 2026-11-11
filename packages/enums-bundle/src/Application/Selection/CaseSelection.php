<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Selection;

use InvalidArgumentException;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\AbstractMetaProperty;
use UnitEnum;

/**
 * A subset of the cases of one enum, declared once and read the same way by a form, a validator constraint and a
 * template: the cases among `only`, minus `except`, in at least one of `groups`, whose meta properties have the
 * values `whereMeta` gives. An empty criterion does not narrow; with none, the selection is every case.
 *
 * A form that offers a subset and the constraint that validates its submission declare the same selection, so the
 * server never accepts a case the form did not show.
 */
final readonly class CaseSelection
{
    /**
     * @param class-string<UnitEnum> $enum
     * @param list<UnitEnum>         $only
     * @param list<UnitEnum>         $except
     * @param list<string>           $groups
     * @param array<class-string<AbstractMetaProperty<*>>, mixed>    $whereMeta
     */
    public function __construct(
        public string $enum,
        public array $only = [],
        public array $except = [],
        public array $groups = [],
        public array $whereMeta = [],
    ) {
        if (!enum_exists($enum)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not an enum.', $enum));
        }

        foreach ([...$only, ...$except] as $case) {
            if (!$case instanceof $enum) {
                throw new InvalidArgumentException(\sprintf('The case %s::%s is not a case of %s.', $case::class, $case->name, $enum));
            }
        }
    }

    /**
     * A selection from options as a form or a template gives them, checked here once: an enum class, lists of its
     * cases, a group or a list of groups, and a map of meta property classes to values.
     *
     * @throws InvalidArgumentException when an option has another shape
     */
    public static function fromOptions(mixed $enum, mixed $only = [], mixed $except = [], mixed $groups = [], mixed $whereMeta = []): self
    {
        if (!\is_string($enum) || !enum_exists($enum)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not an enum.', get_debug_type($enum)));
        }

        return new self($enum, self::caseList($only, 'only'), self::caseList($except, 'except'), self::groupList($groups), self::metaValues($whereMeta));
    }

    /**
     * @return CaseCollection<UnitEnum>
     */
    public function cases(): CaseCollection
    {
        $cases = CaseCollection::of($this->enum::cases());

        if ([] !== $this->only) {
            $cases = $cases->only(...$this->only);
        }

        $cases = $cases->except(...$this->except);

        if ([] !== $this->groups) {
            $cases = $cases->inGroup(...$this->groups);
        }

        foreach ($this->whereMeta as $metaProperty => $value) {
            $cases = $cases->whereMeta($metaProperty, $value);
        }

        return $cases;
    }

    /**
     * @return list<UnitEnum>
     */
    private static function caseList(mixed $cases, string $option): array
    {
        $list = [];

        foreach (\is_array($cases) ? $cases : [null] as $case) {
            if (!$case instanceof UnitEnum) {
                throw new InvalidArgumentException(\sprintf('"%s" is a list of enum cases.', $option));
            }

            $list[] = $case;
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    private static function groupList(mixed $groups): array
    {
        $list = [];

        foreach (\is_array($groups) ? $groups : [$groups] as $group) {
            if (!\is_string($group)) {
                throw new InvalidArgumentException('"group" is a group name or a list of group names.');
            }

            $list[] = $group;
        }

        return $list;
    }

    /**
     * @return array<class-string<AbstractMetaProperty<*>>, mixed>
     */
    private static function metaValues(mixed $whereMeta): array
    {
        if (!\is_array($whereMeta)) {
            throw new InvalidArgumentException('"where_meta" maps meta property classes to values.');
        }

        $values = [];

        foreach ($whereMeta as $class => $value) {
            if (!\is_string($class) || !is_subclass_of($class, AbstractMetaProperty::class)) {
                throw new InvalidArgumentException('"where_meta" maps meta property classes to values.');
            }

            $values[$class] = $value;
        }

        return $values;
    }

    /**
     * Whether any criterion narrows the cases.
     */
    public function narrows(): bool
    {
        return [] !== $this->only || [] !== $this->except || [] !== $this->groups || [] !== $this->whereMeta;
    }
}
