<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

use Phil\Citations\Exception\InvalidReference;

/**
 * A cited document as CSL-JSON keeps it: an id, a type, its names by role, its dates, and its other variables as text
 * — `title`, `container-title`, `publisher`, `publisher-place`, `volume`, `issue`, `page`, `edition`, `DOI`, `URL`
 * and the rest of CSL's variables.
 */
final readonly class Reference
{
    /** @var array<string, list<Name>> */
    private array $names;

    /** @var array<string, CslDate> */
    private array $dates;

    /** @var array<string, string> */
    private array $fields;

    /**
     * @param array<array-key, list<Name>> $names  the names by role, keyed by the value of a NameVariable, in their order
     * @param array<array-key, CslDate>    $dates  the dates, keyed by the value of a DateVariable
     * @param array<array-key, string>     $fields the other variables, by their CSL name
     *
     * @throws InvalidReference an empty id, or a variable name CSL-JSON cannot hold
     */
    public function __construct(
        public string $id,
        public ItemType $type,
        array $names = [],
        array $dates = [],
        array $fields = [],
    ) {
        if ('' === trim($id)) {
            throw InvalidReference::because('the id is empty');
        }

        $kept = [];

        foreach ($names as $role => $list) {
            $role = (string) $role;

            if (!NameVariable::tryFrom($role) instanceof NameVariable) {
                throw InvalidReference::because(\sprintf('"%s" is not a name variable', $role));
            }

            if ([] !== $list) {
                $kept[$role] = $list;
            }
        }

        $this->names = $kept;
        $kept = [];

        foreach ($dates as $variable => $date) {
            $variable = (string) $variable;

            if (!DateVariable::tryFrom($variable) instanceof DateVariable) {
                throw InvalidReference::because(\sprintf('"%s" is not a date variable', $variable));
            }

            $kept[$variable] = $date;
        }

        $this->dates = $kept;
        $kept = [];

        foreach ($fields as $field => $value) {
            $field = (string) $field;
            $reserved = \in_array($field, ['id', 'type'], true) || null !== NameVariable::tryFrom($field) || null !== DateVariable::tryFrom($field);

            if ($reserved || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $field)) {
                throw InvalidReference::because(\sprintf('"%s" is not a variable name', $field));
            }

            if ('' !== trim($value)) {
                $kept[$field] = $value;
            }
        }

        $this->fields = $kept;
    }

    /**
     * @return list<Name>
     */
    public function names(NameVariable $variable): array
    {
        return $this->names[$variable->value] ?? [];
    }

    /**
     * @return array<string, list<Name>>
     */
    public function allNames(): array
    {
        return $this->names;
    }

    public function date(DateVariable $variable): ?CslDate
    {
        return $this->dates[$variable->value] ?? null;
    }

    /**
     * @return array<string, CslDate>
     */
    public function allDates(): array
    {
        return $this->dates;
    }

    public function field(string $name): ?string
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function allFields(): array
    {
        return $this->fields;
    }

    public function title(): ?string
    {
        return $this->field('title');
    }

    public function withId(string $id): self
    {
        return new self($id, $this->type, $this->names, $this->dates, $this->fields);
    }
}
