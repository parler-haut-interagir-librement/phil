<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use DateTimeImmutable;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;

/**
 * A report filed under the code of a scope: who filed it, or nobody when it is anonymous, the members it names, what
 * happened, sealed, the version of the code in force, where it stands, and the responsible people who stepped aside.
 *
 * It holds no plaintext: `Reports::read()` opens its text for a responsible person who may handle it.
 */
final readonly class Report
{
    public const int MAX_NAMED = 20;

    /** @var list<string> */
    public array $named;

    /** @var list<string> */
    public array $recused;

    /**
     * @param list<string> $named   the members the report names, at most 20
     * @param list<string> $recused the responsible people who stepped aside, at most 100
     */
    public function __construct(
        public string $id,
        public string $scope,
        public ?string $reporter,
        array $named,
        public string $sealedText,
        public ?int $codeVersion,
        public DateTimeImmutable $filedAt,
        public ReportStatus $status = ReportStatus::Received,
        public ?Outcome $outcome = null,
        public ?DateTimeImmutable $updatedAt = null,
        array $recused = [],
    ) {
        Identifier::check($id, 'A report');
        Identifier::check($scope, 'A scope');

        if (null !== $reporter) {
            Identifier::check($reporter, 'A reporter');
        }

        $this->named = $this->people($named, self::MAX_NAMED, 'A report names at most %d members.', 'A member named');
        $this->recused = $this->people($recused, Reports::MAX_RESPONSIBLE, 'At most %d responsible people step aside from a report.', 'A responsible person');

        if ((ReportStatus::Resolved === $status) !== ($outcome instanceof Outcome)) {
            throw InvalidCodeOfConduct::because('A report carries an outcome when, and only when, it is resolved.');
        }
    }

    public function names(string $person): bool
    {
        return \in_array($person, $this->named, true);
    }

    public function withStatus(ReportStatus $status, ?Outcome $outcome, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->scope, $this->reporter, $this->named, $this->sealedText, $this->codeVersion, $this->filedAt, $status, $outcome, $at, $this->recused);
    }

    public function withRecusal(string $person, DateTimeImmutable $at): self
    {
        return new self($this->id, $this->scope, $this->reporter, $this->named, $this->sealedText, $this->codeVersion, $this->filedAt, $this->status, $this->outcome, $at, [...$this->recused, $person]);
    }

    /**
     * @param list<string> $people
     *
     * @return list<string>
     */
    private function people(array $people, int $max, string $rule, string $what): array
    {
        $people = array_values(array_unique(array_map(static fn (string $person): string => Identifier::check($person, $what), $people)));

        if (\count($people) > $max) {
            throw InvalidCodeOfConduct::because(\sprintf($rule, $max));
        }

        return $people;
    }
}
