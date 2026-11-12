<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Application\Exception\UnknownPolicy;
use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;

/**
 * The named policies of the configuration, built once per process and never changed: a worker shares them between
 * requests without any risk.
 *
 * @phpstan-type PolicyConfig array{
 *     extends?: ?string,
 *     directives?: array<string, list<string>|bool>,
 *     strict_dynamic?: bool,
 *     report?: bool,
 *     inline?: array{script?: list<string>, style?: list<string>},
 *     trusted_types?: array{enabled?: bool, policies?: list<string>, allow_duplicates?: bool},
 * }
 */
final readonly class PolicyRegistry
{
    /**
     * @param array<string, PolicyDefinition> $definitions
     */
    public function __construct(
        private array $definitions,
    ) {
    }

    /**
     * The policies of the configuration: a policy that extends another takes its directives and replaces those it
     * declares; then the inline code it lists is allowed by its SHA-256, and Trusted Types add their two directives.
     *
     * @param array<string, PolicyConfig> $config
     *
     * @throws InvalidPolicy when a policy is invalid, extends an unknown policy or extends itself through others
     */
    public static function fromConfig(array $config): self
    {
        $policies = [];

        foreach (array_keys($config) as $name) {
            $policies[$name] = self::resolve($config, $name, []);
        }

        $definitions = [];

        foreach ($config as $name => $options) {
            $definitions[$name] = new PolicyDefinition($policies[$name], $options['strict_dynamic'] ?? false, $options['report'] ?? true);
        }

        return new self($definitions);
    }

    /**
     * @throws UnknownPolicy
     */
    public function get(string $name): PolicyDefinition
    {
        return $this->definitions[$name] ?? throw UnknownPolicy::named($name);
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * @param array<string, PolicyConfig> $config
     * @param list<string>                $path   the policies being extended, to find a cycle
     */
    private static function resolve(array $config, string $name, array $path): Policy
    {
        if (\in_array($name, $path, true)) {
            throw new InvalidPolicy(\sprintf('The policy "%s" extends itself: %s.', $name, implode(' → ', [...$path, $name])));
        }

        $options = $config[$name] ?? throw new InvalidPolicy(\sprintf('The policy "%s" extends "%s", which is not declared.', $path[\count($path) - 1] ?? '', $name));
        $policy = new Policy($options['directives'] ?? []);
        $parent = $options['extends'] ?? null;

        if (null !== $parent) {
            $policy = self::resolve($config, $parent, [...$path, $name])->extendedBy($policy);
        }

        foreach (InlineKind::cases() as $kind) {
            $hashes = array_map(SourceSyntax::hashOf(...), $options['inline'][$kind->value] ?? []);

            if ([] !== $hashes) {
                $policy = $policy->withAdded($kind->directive(), $hashes);
            }
        }

        $trustedTypes = $options['trusted_types'] ?? [];

        if ($trustedTypes['enabled'] ?? false) {
            $policy = $policy
                ->withReplaced(Directive::RequireTrustedTypesFor, [])
                ->withReplaced(Directive::TrustedTypes, [...$trustedTypes['policies'] ?? [], ...($trustedTypes['allow_duplicates'] ?? false) ? ["'allow-duplicates'"] : []]);
        }

        return $policy;
    }
}
