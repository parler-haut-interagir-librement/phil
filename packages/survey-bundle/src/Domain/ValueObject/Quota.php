<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Model\Read;

/**
 * A quota: how many responses whose answer meets its condition are taken. A cross quota is a condition over several
 * questions — `{"and": [{"==": [{"var": "age"}, "18-24"]}, {"==": [{"var": "region"}, "north"]}]}` — and a response
 * counts in every quota whose condition it meets. Once one is full, the respondent sees its ending and the response is
 * not kept. The counts are kept by atomic counters, never by counting the responses.
 */
final readonly class Quota
{
    public const int MAX_LIMIT = 1_000_000;

    private const array MEMBERS = ['name', 'limit', 'when', 'ending'];

    /**
     * @param array<string, mixed> $when
     *
     * @throws InvalidQuestionnaire when the limit is out of bounds
     */
    public function __construct(
        public string $name,
        public int $limit,
        public array $when,
        public string $ending,
    ) {
        Read::name($name, 'a quota');
        Read::name($ending, 'the ending of the quota "'.$name.'"');

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidQuestionnaire::because(\sprintf('A quota takes 1 to %d responses: "%s".', self::MAX_LIMIT, $name));
        }
    }

    /**
     * @throws InvalidQuestionnaire when the quota is not of the shape `toArray()` writes
     */
    public static function fromArray(mixed $data): self
    {
        $quota = Read::map($data, 'a quota', self::MEMBERS);

        return new self(
            Read::string($quota, 'name', 'a quota'),
            Read::optionalInt($quota, 'limit', 'a quota') ?? throw InvalidQuestionnaire::because('A quota has its limit.'),
            Read::condition($quota, 'when', 'a quota') ?? throw InvalidQuestionnaire::because('A quota has its condition.'),
            Read::string($quota, 'ending', 'a quota'),
        );
    }

    /**
     * @return array{name: string, limit: int, when: array<string, mixed>, ending: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'limit' => $this->limit, 'when' => $this->when, 'ending' => $this->ending];
    }
}
