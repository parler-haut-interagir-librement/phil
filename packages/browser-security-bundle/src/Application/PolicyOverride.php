<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;

/**
 * What a route or a controller changes in the policy of its responses: another named policy, sources added to or
 * replacing those of a directive, directives removed, the policy sent as report-only or enforced, or no policy at all.
 */
final readonly class PolicyOverride
{
    /**
     * @param array<array-key, list<string>> $add     sources added, by directive
     * @param array<array-key, list<string>> $replace sources replacing those of the directive, by directive
     * @param list<string>                   $remove  directives removed
     */
    public function __construct(
        public ?string $policy = null,
        public array $add = [],
        public array $replace = [],
        public array $remove = [],
        public ?bool $reportOnly = null,
        public bool $disabled = false,
    ) {
    }

    /**
     * The override a route declares in its `_csp` default: a map of the same keys, `report_only` for
     * `reportOnly`.
     *
     * @throws InvalidPolicy when the value is not such a map
     */
    public static function fromArray(mixed $value): self
    {
        if (!\is_array($value)) {
            throw new InvalidPolicy('The "_csp" default of a route is a map of policy, add, replace, remove, report_only and disabled.');
        }

        $unknown = array_diff(array_keys($value), ['policy', 'add', 'replace', 'remove', 'report_only', 'disabled']);

        if ([] !== $unknown) {
            throw new InvalidPolicy(\sprintf('The "_csp" default of a route does not take "%s".', implode('", "', $unknown)));
        }

        $policy = $value['policy'] ?? null;
        $reportOnly = $value['report_only'] ?? null;
        $disabled = $value['disabled'] ?? false;

        if ((null !== $policy && !\is_string($policy)) || (null !== $reportOnly && !\is_bool($reportOnly)) || !\is_bool($disabled)) {
            throw new InvalidPolicy('The "policy" of a "_csp" default is a name, and its "report_only" and "disabled" booleans.');
        }

        return new self($policy, self::sources($value['add'] ?? []), self::sources($value['replace'] ?? []), self::names($value['remove'] ?? []), $reportOnly, $disabled);
    }

    /**
     * The policy with the sources added, replaced and removed; the named policy and the mode are the header builder's.
     *
     * @throws InvalidPolicy when a directive or a source is invalid
     */
    public function apply(Policy $policy): Policy
    {
        foreach ($this->replace as $directive => $sources) {
            $policy = $policy->withReplaced((string) $directive, $sources);
        }

        foreach ($this->add as $directive => $sources) {
            $policy = $policy->withAdded((string) $directive, $sources);
        }

        foreach ($this->remove as $directive) {
            $policy = $policy->without($directive);
        }

        return $policy;
    }

    /**
     * @return array<array-key, list<string>>
     */
    private static function sources(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new InvalidPolicy('The "add" and "replace" of a "_csp" default are maps of sources by directive.');
        }

        $sources = [];

        foreach ($value as $directive => $list) {
            $sources[$directive] = self::names($list);
        }

        return $sources;
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value) || array_any($value, static fn (mixed $item): bool => !\is_string($item))) {
            throw new InvalidPolicy('A list of a "_csp" default holds strings only.');
        }

        /** @var list<string> $value */
        return $value;
    }
}
