<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * One step of a definition, of one of four kinds:
 *
 * - an action of a type the catalogue holds, with its options, tried again up to `retries` times when it fails in a
 *   way worth retrying;
 * - a delay of a number of seconds, after which the run goes on;
 * - a filter, which ends the run, without failure, unless its condition holds;
 * - a branch, which follows `then` when its condition holds and `else` otherwise, then the steps after it.
 */
final readonly class Step
{
    public const int MAX_RETRIES = 10;

    public const int MAX_DELAY_SECONDS = 31_622_400;

    /**
     * @param list<self> $then
     * @param list<self> $else
     */
    private function __construct(
        public string $id,
        public StepKind $kind,
        public ?string $action,
        public Options $options,
        public int $retries = 0,
        public int $seconds = 0,
        public ?Condition $condition = null,
        public array $then = [],
        public array $else = [],
    ) {
    }

    /**
     * @throws InvalidDefinition when the id, the type or the number of retries is refused
     */
    public static function action(string $id, string $type, ?Options $options = null, int $retries = 0): self
    {
        if ($retries < 0 || $retries > self::MAX_RETRIES) {
            throw InvalidDefinition::because(\sprintf('The step "%s" retries between 0 and %d times.', $id, self::MAX_RETRIES));
        }

        return new self(self::checkedId($id), StepKind::Action, ComponentType::check($type), $options ?? Options::none(), $retries);
    }

    /**
     * @throws InvalidDefinition when the id or the delay is refused
     */
    public static function delay(string $id, int $seconds): self
    {
        if ($seconds < 1 || $seconds > self::MAX_DELAY_SECONDS) {
            throw InvalidDefinition::because(\sprintf('The delay "%s" lasts between 1 second and %d seconds (366 days).', $id, self::MAX_DELAY_SECONDS));
        }

        return new self(self::checkedId($id), StepKind::Delay, null, Options::none(), seconds: $seconds);
    }

    /**
     * @throws InvalidDefinition when the id is refused
     */
    public static function filter(string $id, Condition $condition): self
    {
        return new self(self::checkedId($id), StepKind::Filter, null, Options::none(), condition: $condition);
    }

    /**
     * @param list<self> $then
     * @param list<self> $else
     *
     * @throws InvalidDefinition when the id is refused
     */
    public static function branch(string $id, Condition $condition, array $then, array $else = []): self
    {
        return new self(self::checkedId($id), StepKind::Branch, null, Options::none(), condition: $condition, then: $then, else: $else);
    }

    /**
     * The steps of one path of a branch; none for another kind.
     *
     * @return list<self>
     */
    public function path(BranchPath $path): array
    {
        return BranchPath::Then === $path ? $this->then : $this->else;
    }

    private static function checkedId(string $id): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a step id: a lowercase letter, then up to 63 lowercase letters, digits, "_" or "-".', $id));
        }

        return $id;
    }
}
