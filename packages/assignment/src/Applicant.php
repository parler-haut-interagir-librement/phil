<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Internal\Identifier;

/**
 * Who is placed as one: a person, or a group whose members stay together, with the slots they want ranked from the
 * most wanted. A group takes as many seats as it has members, all in the same slot.
 */
final readonly class Applicant
{
    public string $id;

    /** @var non-empty-list<string> */
    public array $members;

    /** @var list<string> */
    public array $choices;

    /**
     * @param list<string> $members the people of the applicant; empty for a person whose identifier is the applicant's
     * @param list<string> $choices slot identifiers, the most wanted first
     *
     * @throws InvalidProblem
     */
    public function __construct(string $id, array $members = [], array $choices = [])
    {
        $this->id = Identifier::check($id, 'applicant');
        $people = [] === $members ? [$id] : $members;

        foreach ($people as $member) {
            Identifier::check($member, 'member');
        }

        if (\count(array_unique($people)) !== \count($people)) {
            throw InvalidProblem::because(\sprintf('Applicant "%s" names a member twice.', $id));
        }

        foreach ($choices as $choice) {
            Identifier::check($choice, 'slot');
        }

        if (\count(array_unique($choices)) !== \count($choices)) {
            throw InvalidProblem::because(\sprintf('Applicant "%s" ranks a slot twice.', $id));
        }

        $this->members = $people;
        $this->choices = $choices;
    }

    /**
     * A person on their own.
     *
     * @param list<string> $choices
     */
    public static function person(string $id, array $choices): self
    {
        return new self($id, [], $choices);
    }

    /**
     * People who stay together.
     *
     * @param non-empty-list<string> $members
     * @param list<string>           $choices
     */
    public static function group(string $id, array $members, array $choices): self
    {
        return new self($id, $members, $choices);
    }

    /**
     * The seats the applicant takes.
     */
    public function size(): int
    {
        return \count($this->members);
    }

    /**
     * The rank of a slot among the choices, from 1, or null when the slot is not ranked.
     *
     * @return positive-int|null
     */
    public function rankOf(string $slot): ?int
    {
        $index = array_search($slot, $this->choices, true);

        return false === $index ? null : $index + 1;
    }
}
