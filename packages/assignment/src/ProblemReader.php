<?php

declare(strict_types=1);

namespace Phil\Assignment;

use JsonException;
use Phil\Assignment\Exception\InvalidProblem;

/**
 * Reads a problem from plain data — decoded JSON, a form, a row —, checking every field and every bound before the
 * problem exists; anything else than the expected shape is refused with `InvalidProblem`, never a PHP error.
 *
 * ```json
 * {
 *   "slots": [{"id": "table-1", "capacity": 5, "minimum": 3}],
 *   "applicants": [{"id": "ana", "choices": ["table-1"]}, {"id": "g1", "members": ["bo", "cy"], "choices": []}],
 *   "forbidden": [{"applicant": "ana", "slot": "table-2"}],
 *   "forced": [{"applicant": "g1", "slot": "table-1"}]
 * }
 * ```
 */
final readonly class ProblemReader
{
    /** The longest JSON text read, in bytes. */
    public const int MAX_JSON_BYTES = 16_777_216;

    public function __construct(
        private Limits $limits = new Limits(),
    ) {
    }

    /**
     * @throws InvalidProblem
     */
    public function fromJson(string $json): Problem
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidProblem::because(\sprintf('A problem in JSON is at most %d bytes.', self::MAX_JSON_BYTES));
        }

        try {
            $data = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidProblem::because(\JSON_ERROR_DEPTH === $jsonException->getCode() ? 'The problem is nested deeper than its shape allows.' : 'The problem is not valid JSON.');
        }

        if (!\is_array($data)) {
            throw InvalidProblem::because('A problem is a JSON object.');
        }

        return $this->fromArray($data);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidProblem
     */
    public function fromArray(array $data): Problem
    {
        $slots = [];

        foreach ($this->list($data, 'slots', $this->limits->slots) as $slot) {
            $minimum = $slot['minimum'] ?? 0;
            $slots[] = new Slot($this->string($slot, 'id'), $this->integer($slot, 'capacity'), \is_int($minimum) ? $minimum : throw InvalidProblem::because('The minimum of a slot is an integer.'));
        }

        $applicants = [];

        foreach ($this->list($data, 'applicants', $this->limits->applicants) as $applicant) {
            $applicants[] = new Applicant(
                $this->string($applicant, 'id'),
                $this->strings($applicant, 'members', $this->limits->members),
                $this->strings($applicant, 'choices', $this->limits->choices),
            );
        }

        return new Problem($slots, $applicants, $this->pairs($data, 'forbidden'), $this->pairs($data, 'forced'), $this->limits);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<Pair>
     */
    private function pairs(array $data, string $key): array
    {
        $pairs = [];

        foreach ($this->list($data, $key, $this->limits->applicants * $this->limits->choices) as $pair) {
            $pairs[] = new Pair($this->string($pair, 'applicant'), $this->string($pair, 'slot'));
        }

        return $pairs;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private function list(array $data, string $key, int $limit): array
    {
        $items = $data[$key] ?? [];

        if (!\is_array($items) || !array_is_list($items)) {
            throw InvalidProblem::because(\sprintf('"%s" is a list.', $key));
        }

        if (\count($items) > $limit) {
            throw InvalidProblem::because(\sprintf('"%s" has at most %d items.', $key, $limit));
        }

        foreach ($items as $item) {
            if (!\is_array($item)) {
                throw InvalidProblem::because(\sprintf('Each item of "%s" is an object.', $key));
            }
        }

        /** @var list<array<mixed>> $items */
        return $items;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function strings(array $data, string $key, int $limit): array
    {
        $items = $data[$key] ?? [];

        if (!\is_array($items) || !array_is_list($items) || \count($items) > $limit) {
            throw InvalidProblem::because(\sprintf('"%s" is a list of at most %d strings.', $key, $limit));
        }

        foreach ($items as $item) {
            if (!\is_string($item)) {
                throw InvalidProblem::because(\sprintf('"%s" is a list of strings.', $key));
            }
        }

        /** @var list<string> $items */
        return $items;
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidProblem::because(\sprintf('"%s" is a string.', $key));
    }

    /**
     * @param array<mixed> $data
     */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw InvalidProblem::because(\sprintf('"%s" is an integer.', $key));
    }
}
